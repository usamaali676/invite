<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApiApprovalStatus;
use App\Exceptions\GoogleApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgencySettingsRequest;
use App\Services\Google\GoogleAgencyService;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(GoogleAgencyService $agency, GoogleOAuthService $oauth): View
    {
        $connection = $agency->connection();
        $detected = [];
        $detectError = null;

        if ($connection !== null) {
            try {
                $detected = $agency->detectAgencyAccounts();
            } catch (GoogleApiException $e) {
                $detectError = $e->adminMessage();
            }
        }

        return view('admin.settings', [
            'settings' => $agency->settings(),
            'checks' => $agency->readiness(),
            'connection' => $connection,
            'detected' => $detected,
            'detectError' => $detectError,
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
}
