<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccessMethod;
use App\Enums\ClientStatus;
use App\Exceptions\GoogleApiException;
use App\Http\Controllers\Controller;
use App\Models\GbpClient;
use App\Services\Google\GoogleAccessVerificationService;
use App\Services\Google\GoogleAgencyService;
use App\Services\Google\GoogleInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InvitationController extends Controller
{
    public function index(GoogleAgencyService $agency): View
    {
        $settings = $agency->settings();
        $invitations = [];
        $problem = null;

        if ($agency->connection() === null) {
            $problem = 'Connect the FTS Google account in Settings to see invitations waiting for the Location Group.';
        } elseif (! $settings->hasLocationGroup()) {
            $problem = 'Save the FTS Location Group ID in Settings first.';
        } else {
            try {
                $invitations = $agency->pendingInvitations();
            } catch (GoogleApiException $e) {
                $problem = $e->adminMessage();
            }
        }

        $waitingClients = GbpClient::query()
            ->whereIn('access_status', [ClientStatus::RequestSubmitted->value, ClientStatus::PendingApproval->value])
            ->latest('id')
            ->get();

        $invitations = collect($invitations)->map(fn (array $invitation) => $invitation + [
            'client' => $waitingClients->first(fn (GbpClient $client) => GoogleAccessVerificationService::normalizeTitle($client->google_location_title ?: $client->business_name)
                === GoogleAccessVerificationService::normalizeTitle($invitation['location'])),
        ]);

        return view('admin.invitations', [
            'invitations' => $invitations,
            'problem' => $problem,
            'waitingClients' => $waitingClients->filter(fn (GbpClient $client) => $client->access_method === AccessMethod::Api),
            'locationGroup' => $settings->locationGroupResourceName(),
        ]);
    }

    public function accept(Request $request, GoogleInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'invitation' => ['required', 'string', 'max:255', 'regex:#^accounts/[A-Za-z0-9_-]+/invitations/[A-Za-z0-9_-]+$#'],
        ]);

        try {
            $outcome = $invitations->approveAgencyInvitation($data['invitation']);
        } catch (GoogleApiException $e) {
            return back()->with('error', $e->adminMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($outcome['connected'] !== []) {
            return back()->with('status', 'Approved. '.implode(', ', $outcome['connected']).' is now connected.');
        }

        if ($outcome['waiting'] !== []) {
            return back()->with('notice', 'Approved for '.$outcome['business'].'. Google has not shown the new access yet, so the client stays "Awaiting approval". It updates automatically within a minute, or use Refresh status.');
        }

        return back()->with('status', 'Approved for '.$outcome['business'].'. No client in this dashboard matched that business name, so no status changed.');
    }
}
