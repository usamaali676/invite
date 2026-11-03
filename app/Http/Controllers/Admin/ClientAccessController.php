<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Exceptions\GoogleApiException;
use App\Http\Controllers\Controller;
use App\Mail\OnboardingLinkMail;
use App\Models\GbpAccessRequest;
use App\Models\GbpAgencySetting;
use App\Models\GbpClient;
use App\Services\Google\GoogleAccessVerificationService;
use App\Services\Google\GoogleInvitationService;
use App\Services\Google\GoogleTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ClientAccessController extends Controller
{
    public function refresh(Request $request, GbpClient $client, GoogleAccessVerificationService $verification): RedirectResponse
    {
        $accessRequest = $client->accessRequests()->whereIn('status', [
            AccessRequestStatus::Submitted->value,
            AccessRequestStatus::PendingApproval->value,
            AccessRequestStatus::Connected->value,
        ])->latest('id')->first();

        if ($accessRequest === null) {
            return back()->with('error', 'There is no submitted request to check yet.');
        }

        $result = $verification->refresh($accessRequest, ActorType::Admin);

        return back()->with($result->isConnected() ? 'status' : 'notice', $result->detail);
    }

    public function retry(Request $request, GbpClient $client, GoogleInvitationService $invitations): RedirectResponse
    {
        $failed = $client->accessRequests()->where('status', AccessRequestStatus::Failed->value)
            ->where('access_method', AccessMethod::Api->value)->latest('id')->first();

        if ($failed === null) {
            return back()->with('error', 'There is no failed request to retry.');
        }

        try {
            $result = $invitations->retry($failed);
        } catch (GoogleApiException $e) {
            return back()->with('error', 'The client\'s Google authorization is no longer available. Create a new link and ask the client to connect again.');
        }

        return $result->status === AccessRequestStatus::Failed
            ? back()->with('error', 'Retry failed: '.$result->error_message)
            : back()->with('status', 'Request sent to Google again. Current status: '.$result->status->value.'.');
    }

    public function changeMethod(Request $request, GbpClient $client): RedirectResponse
    {
        $data = $request->validate(['access_method' => ['required', Rule::enum(AccessMethod::class)]]);

        if (in_array($client->access_status, [ClientStatus::RequestSubmitted, ClientStatus::PendingApproval, ClientStatus::Connected], true)) {
            return back()->with('error', 'The method cannot change while a request is submitted or connected.');
        }

        $method = AccessMethod::from($data['access_method']);
        $client->update(['access_method' => $method]);
        $client->logEvent('client.method_changed', ActorType::Admin, 'Access method set to '.$method->label().'.');

        return back()->with('status', 'Access method changed to '.$method->label().'.');
    }

    public function disconnectGoogle(Request $request, GbpClient $client, GoogleTokenService $tokens): RedirectResponse
    {
        $connection = $client->activeClientConnection();

        if ($connection === null) {
            return back()->with('error', 'No active Google authorization is stored for this client.');
        }

        $tokens->disconnect($connection);
        $client->logEvent('google.disconnected', ActorType::Admin, 'Client Google authorization revoked by '.$request->user()->name.'.');

        return back()->with('status', 'The client\'s Google authorization was revoked and the stored tokens were deleted.');
    }

    /** The FTS employee has sent the agency request through Business Profile Manager. */
    public function manualSubmitted(Request $request, GbpClient $client): RedirectResponse
    {
        $request->validate(['email_client' => ['nullable', 'boolean']]);
        $account = GbpAgencySetting::current()->locationGroupResourceName();

        if ($account === null) {
            return back()->with('error', 'Save the FTS Location Group ID in Settings before recording an agency request.');
        }

        if ($client->accessRequests()->where('access_method', AccessMethod::AgencyManual->value)
            ->whereIn('status', [AccessRequestStatus::Submitted->value, AccessRequestStatus::PendingApproval->value, AccessRequestStatus::Connected->value])->exists()) {
            return back()->with('error', 'An agency request is already recorded for this client.');
        }

        $accessRequest = $client->accessRequests()->create([
            'access_method' => AccessMethod::AgencyManual,
            'requested_account' => $account,
            'requested_role' => 'MANAGER',
            'status' => AccessRequestStatus::PendingApproval,
            'submitted_at' => now(),
        ]);

        $client->update(['access_method' => AccessMethod::AgencyManual]);
        $client->tryTransitionTo(ClientStatus::RequestSubmitted);
        $client->tryTransitionTo(ClientStatus::PendingApproval);
        $client->logEvent('request.manual_submitted', ActorType::Admin, 'Agency request recorded as submitted in Business Profile Manager by '.$request->user()->name.'.', [], $accessRequest);

        $emailed = false;
        $link = $client->activeLink();

        if ($request->boolean('email_client') && filled($client->client_email) && $link?->isUsable() && $link->url()) {
            Mail::to($client->client_email)->queue(new OnboardingLinkMail($client, $link->url(), manual: true));
            $client->logEvent('link.emailed', ActorType::Admin, 'Approval instructions emailed to '.$client->client_email.'.');
            $emailed = true;
        }

        return back()->with('status', 'Recorded. The client now shows as awaiting approval.'.($emailed ? ' Instructions were emailed to them.' : ''));
    }

    /**
     * Mark a manual request as connected, only after Google confirms access,
     * or after staff confirm they saw it themselves in Business Profile Manager.
     */
    public function manualConnected(Request $request, GbpClient $client, GoogleAccessVerificationService $verification): RedirectResponse
    {
        $accessRequest = $client->accessRequests()
            ->where('access_method', AccessMethod::AgencyManual->value)
            ->whereIn('status', [AccessRequestStatus::Submitted->value, AccessRequestStatus::PendingApproval->value])
            ->latest('id')->first();

        if ($accessRequest === null) {
            return back()->with('error', 'Record the agency request as submitted first.');
        }

        $result = $verification->refresh($accessRequest, ActorType::Admin);

        if ($result->isConnected()) {
            return back()->with('status', 'Google confirms access. The client is now connected.');
        }

        if (! $request->boolean('confirmed')) {
            return back()->with('error', 'Google does not show access yet. If you have checked Business Profile Manager and FTS has Manager access, tick the confirmation box and try again.');
        }

        $this->confirmManually($accessRequest, $request->user()->name);

        return back()->with('status', 'Marked as connected on your confirmation.');
    }

    private function confirmManually(GbpAccessRequest $accessRequest, string $adminName): void
    {
        $accessRequest->forceFill([
            'status' => AccessRequestStatus::Connected,
            'approved_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $client = $accessRequest->client;
        $client->tryTransitionTo(ClientStatus::PendingApproval);
        $client->tryTransitionTo(ClientStatus::Connected);
        $client->logEvent('request.manually_confirmed', ActorType::Admin, $adminName.' confirmed in Business Profile Manager that FTS has Manager access.', [], $accessRequest);
    }
}
