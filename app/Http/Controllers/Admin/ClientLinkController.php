<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Mail\OnboardingLinkMail;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ClientLinkController extends Controller
{
    /** Generate a first link, or replace the current one (the old link stops working at once). */
    public function store(Request $request, GbpClient $client): RedirectResponse
    {
        $regenerated = $client->activeLink() !== null;

        $link = GbpOnboardingLink::issue($client, $request->user());
        $client->tryTransitionTo(ClientStatus::LinkSent);
        $client->logEvent(
            $regenerated ? 'link.regenerated' : 'link.generated',
            ActorType::Admin,
            ($regenerated ? 'Onboarding link replaced' : 'Onboarding link generated').' by '.$request->user()->name.'.',
        );

        return redirect()->route('admin.clients.show', $client)
            ->with('status', $regenerated ? 'New link created. The previous link no longer works.' : 'Link created. Copy it and send it to the client.')
            ->with('highlight_link', $link->url());
    }

    public function destroy(Request $request, GbpClient $client): RedirectResponse
    {
        $revoked = $client->onboardingLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        if ($revoked === 0) {
            return back()->with('error', 'There is no active link to revoke.');
        }

        if (in_array($client->access_status, [
            ClientStatus::New, ClientStatus::LinkSent, ClientStatus::LinkOpened, ClientStatus::OauthConnected,
            ClientStatus::ProfileSelected, ClientStatus::Failed, ClientStatus::Expired, ClientStatus::Rejected,
        ], true)) {
            $client->tryTransitionTo(ClientStatus::Revoked);
        }

        $client->logEvent('link.revoked', ActorType::Admin, 'Onboarding link revoked by '.$request->user()->name.'.');

        return back()->with('status', 'Link revoked. It can no longer be opened.');
    }

    public function email(Request $request, GbpClient $client): RedirectResponse
    {
        if (blank($client->client_email)) {
            return back()->with('error', 'Add the client\'s email address first (Edit), then send the link.');
        }

        $link = $client->activeLink();

        if ($link === null || ! $link->isUsable() || $link->url() === null) {
            return back()->with('error', 'Create a fresh link first, then send the email.');
        }

        Mail::to($client->client_email)->queue(new OnboardingLinkMail($client, $link->url()));
        $client->logEvent('link.emailed', ActorType::Admin, 'Link emailed to '.$client->client_email.' by '.$request->user()->name.'.');

        return back()->with('status', 'Email queued for '.$client->client_email.'.');
    }
}
