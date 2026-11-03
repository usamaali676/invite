<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConnectionType;
use App\Http\Controllers\Controller;
use App\Services\Google\GoogleAgencyService;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgencyGoogleController extends Controller
{
    public function connect(Request $request, GoogleOAuthService $oauth): RedirectResponse
    {
        if (! $oauth->isConfigured()) {
            return redirect()->route('admin.settings.edit')
                ->with('error', 'Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env first.');
        }

        return redirect()->away($oauth->beginAuthorization($request, ConnectionType::Agency, ['user_id' => $request->user()->id]));
    }

    public function disconnect(GoogleAgencyService $agency): RedirectResponse
    {
        $agency->disconnect();

        return redirect()->route('admin.settings.edit')->with('status', 'The FTS Google account was disconnected and its tokens deleted.');
    }
}
