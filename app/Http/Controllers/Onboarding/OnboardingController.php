<?php

namespace App\Http\Controllers\Onboarding;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Exceptions\GoogleApiException;
use App\Http\Controllers\Controller;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use App\Models\GbpOnboardingLink;
use App\Services\Google\GoogleAccountService;
use App\Services\Google\GoogleAgencyService;
use App\Services\Google\GoogleInvitationService;
use App\Services\Google\GoogleLocationService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        private GoogleOAuthService $oauth,
        private GoogleTokenService $tokens,
        private GoogleAccountService $accounts,
        private GoogleLocationService $locations,
        private GoogleInvitationService $invitations,
        private GoogleAgencyService $agency,
    ) {}

    /** Landing page, or the right "where things stand" screen if the client already went further. */
    public function show(Request $request): View|RedirectResponse
    {
        $link = $this->link($request);
        $client = $link->client;

        if ($link->opened_at === null) {
            $link->forceFill(['opened_at' => now()])->save();
            $client->tryTransitionTo(ClientStatus::LinkOpened);
            $client->logEvent('link.opened', ActorType::Client, 'The client opened the onboarding link.');
        }

        if ($client->access_method === AccessMethod::AgencyManual) {
            return view('onboarding.agency-request', $this->shared($client, 3) + [
                'submitted' => $client->access_status->isPending() || $client->access_status === ClientStatus::Connected,
                'connected' => $client->access_status === ClientStatus::Connected,
            ]);
        }

        $status = $client->access_status;

        if (in_array($status, [ClientStatus::RequestSubmitted, ClientStatus::PendingApproval, ClientStatus::Connected], true)) {
            return view('onboarding.status', $this->shared($client, 4) + [
                'state' => match (true) {
                    $status === ClientStatus::Connected => 'connected',
                    $request->session()->pull('gbp.just_submitted') === true => 'submitted',
                    default => 'pending',
                },
            ]);
        }

        if (in_array($status, [ClientStatus::Failed, ClientStatus::Rejected], true)
            && $client->accessRequests()->whereIn('status', [AccessRequestStatus::Failed->value, AccessRequestStatus::Rejected->value])->exists()) {
            return view('onboarding.status', $this->shared($client, 4) + ['state' => 'error']);
        }

        if (in_array($status, [ClientStatus::OauthConnected, ClientStatus::ProfileSelected], true) && $this->boundConnection($request, $link, $client) !== null) {
            return redirect()->route('onboarding.profiles', ['token' => $request->route('token')]);
        }

        return view('onboarding.welcome', $this->shared($client, 1) + [
            'denied' => (bool) $request->session()->get('oauth_denied'),
            'problem' => (bool) $request->session()->get('problem'),
            'available' => $this->agency->canRunAutomaticFlow(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $link = $this->link($request);
        $client = $link->client;
        $token = $request->route('token');

        if ($client->access_method !== AccessMethod::Api || ! $this->agency->canRunAutomaticFlow()
            || $client->access_status->isPending() || $client->access_status === ClientStatus::Connected) {
            return redirect()->route('onboarding.show', ['token' => $token]);
        }

        $url = $this->oauth->beginAuthorization($request, ConnectionType::Client, [
            'link_id' => $link->id,
            'client_id' => $client->id,
        ]);

        return redirect()->away($url);
    }

    public function profiles(Request $request): View|RedirectResponse
    {
        [$link, $client, $connection] = $this->requireConnection($request);

        if ($connection === null) {
            return $this->backToStart($request);
        }

        try {
            $listing = $this->listLocations($connection);
        } catch (GoogleApiException $e) {
            return $this->friendlyFailure($request, $link, $client, $connection, $e);
        }

        if ($listing === []) {
            $client->logEvent('profiles.none', ActorType::Client, 'No Business Profile was found for the signed-in Google account.');

            return view('onboarding.no-profiles', $this->shared($client, 2) + ['googleName' => $connection->google_account_name]);
        }

        $request->session()->put('gbp.single.'.$link->id, count($listing) === 1);

        if (count($listing) === 1) {
            $this->storeSelection($client, $listing[0]);

            return redirect()->route('onboarding.confirm', ['token' => $request->route('token')]);
        }

        return view('onboarding.profiles', $this->shared($client, 2) + [
            'locations' => $listing,
            'selected' => $client->location_resource_name,
            'googleName' => $connection->google_account_name,
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        [$link, $client, $connection] = $this->requireConnection($request);

        if ($connection === null) {
            return $this->backToStart($request);
        }

        $data = $request->validate(['location' => ['required', 'string', 'max:128', 'regex:#^locations/[A-Za-z0-9_-]+$#']]);

        try {
            $listing = $this->listLocations($connection);
        } catch (GoogleApiException $e) {
            return $this->friendlyFailure($request, $link, $client, $connection, $e);
        }

        $chosen = collect($listing)->firstWhere('name', $data['location']);

        if ($chosen === null) {
            return back()->withErrors(['location' => 'Please choose one of your businesses from the list.']);
        }

        $this->storeSelection($client, $chosen);

        return redirect()->route('onboarding.confirm', ['token' => $request->route('token')]);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        [$link, $client, $connection] = $this->requireConnection($request);

        if ($connection === null) {
            return $this->backToStart($request);
        }

        if (! $client->hasSelectedLocation()) {
            return redirect()->route('onboarding.profiles', ['token' => $request->route('token')]);
        }

        return view('onboarding.confirm', $this->shared($client, 3) + [
            'only' => (bool) $request->session()->get('gbp.single.'.$link->id),
            'googleName' => $connection->google_account_name,
        ]);
    }

    public function authorizeRequest(Request $request): RedirectResponse
    {
        [$link, $client, $connection] = $this->requireConnection($request);

        if ($connection === null) {
            return $this->backToStart($request);
        }

        $request->validate(['authorize' => ['accepted']], [
            'authorize.accepted' => 'Please tick the box to confirm you agree.',
        ]);

        if (! $client->hasSelectedLocation()) {
            return redirect()->route('onboarding.profiles', ['token' => $request->route('token')]);
        }

        $client->logEvent('request.authorized', ActorType::Client, 'The client ticked the authorization box and confirmed the request.');

        $result = $this->invitations->requestManagerAccess($client, $connection);

        if ($result->status !== AccessRequestStatus::Failed) {
            $request->session()->flash('gbp.just_submitted', true);
        }

        return redirect()->route('onboarding.show', ['token' => $request->route('token')]);
    }

    /**
     * @return array{0: GbpOnboardingLink, 1: GbpClient, 2: ?GbpGoogleConnection}
     */
    private function requireConnection(Request $request): array
    {
        $link = $this->link($request);
        $client = $link->client;

        return [$link, $client, $this->boundConnection($request, $link, $client)];
    }

    /** The Google authorization this browser session created for this link, if still valid. */
    private function boundConnection(Request $request, GbpOnboardingLink $link, GbpClient $client): ?GbpGoogleConnection
    {
        $id = $request->session()->get('gbp.connection.'.$link->id);

        if (! $id || $client->access_method !== AccessMethod::Api || $client->access_status->isPending() || $client->access_status === ClientStatus::Connected) {
            return null;
        }

        return GbpGoogleConnection::query()
            ->whereKey($id)
            ->where('gbp_client_id', $client->id)
            ->where('connection_type', ConnectionType::Client->value)
            ->where('status', ConnectionStatus::Active->value)
            ->first();
    }

    /**
     * @return array<int, array{account: string, name: string, id: string, title: string, address: string}>
     *
     * @throws GoogleApiException
     */
    private function listLocations(GbpGoogleConnection $connection): array
    {
        $token = $this->tokens->validAccessToken($connection);

        return $this->locations->listForAccounts($token, $this->accounts->listAccounts($token));
    }

    /**
     * @param  array{account: string, name: string, id: string, title: string, address: string}  $location
     */
    private function storeSelection(GbpClient $client, array $location): void
    {
        $client->update([
            'google_account_id' => $location['account'],
            'google_location_id' => $location['id'],
            'location_resource_name' => $location['name'],
            'google_location_title' => $location['title'],
            'google_location_address' => $location['address'],
        ]);

        $client->tryTransitionTo(ClientStatus::ProfileSelected);
        $client->logEvent('profile.selected', ActorType::Client, 'The client selected "'.$location['title'].'".', ['location' => $location['name']]);
    }

    private function friendlyFailure(Request $request, GbpOnboardingLink $link, GbpClient $client, GbpGoogleConnection $connection, GoogleApiException $e): RedirectResponse
    {
        $client->logEvent('google.error', ActorType::System, $e->adminMessage(), ['error' => $e->type->value]);

        if ($e->type->needsNewAuthorization()) {
            $this->tokens->markExpired($connection);
            $request->session()->forget('gbp.connection.'.$link->id);
        } else {
            $client->tryTransitionTo(ClientStatus::Failed);
        }

        return redirect()->route('onboarding.show', ['token' => $request->route('token')])->with('problem', true);
    }

    private function backToStart(Request $request): RedirectResponse
    {
        return redirect()->route('onboarding.show', ['token' => $request->route('token')]);
    }

    private function link(Request $request): GbpOnboardingLink
    {
        return $request->attributes->get('onboarding_link');
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(GbpClient $client, int $step): array
    {
        return [
            'client' => $client,
            'step' => $step,
            'agency' => config('gbp.agency_name'),
            'token' => request()->route('token'),
        ];
    }
}
