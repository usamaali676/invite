<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApiApprovalStatus;
use App\Exceptions\GoogleApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgencySettingsRequest;
use App\Services\Google\GoogleAgencyService;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(GoogleAgencyService $agency, GoogleOAuthService $oauth): View
    {
        $connection = $agency->connection();
        $detected = [];
        $detectError = null;
        $invitations = [];
        $invitationError = null;

        if ($connection !== null) {
            try {
                $detected = $agency->detectAgencyAccounts();
            } catch (GoogleApiException $e) {
                $detectError = $e->adminMessage();
            }

            if ($agency->settings()->hasLocationGroup()) {
                try {
                    $invitations = $agency->pendingInvitations();
                } catch (GoogleApiException $e) {
                    $invitationError = $e->adminMessage();
                }
            }
        }

        return view('admin.settings', [
            'settings' => $agency->settings(),
            'checks' => $agency->readiness(),
            'connection' => $connection,
            'detected' => $detected,
            'detectError' => $detectError,
            'invitations' => $invitations,
            'invitationError' => $invitationError,
            'redirectUri' => $oauth->redirectUri(),
            'approvalStatuses' => ApiApprovalStatus::cases(),
            'oauthConfigured' => $oauth->isConfigured(),
        ]);
    }

    public function update(AgencySettingsRequest $request, GoogleAgencyService $agency): RedirectResponse
    {
        $agency->settings()->update([...$request->validated(), 'default_role' => 'MANAGER']);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    public function test(GoogleAgencyService $agency): RedirectResponse
    {
        $result = $agency->testApiConnection();

        return redirect()->route('admin.settings.edit')->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function acceptInvitation(Request $request, GoogleAgencyService $agency): RedirectResponse
    {
        $data = $request->validate(['invitation' => ['required', 'string', 'max:255', 'regex:#^accounts/[A-Za-z0-9_-]+/invitations/[A-Za-z0-9_-]+$#']]);

        try {
            $agency->acceptInvitation($data['invitation']);
        } catch (GoogleApiException $e) {
            return back()->with('error', $e->adminMessage());
        }

        return back()->with('status', 'Invitation approved for the FTS Location Group. Use "Refresh status" on the client to confirm access.');
    }
}
