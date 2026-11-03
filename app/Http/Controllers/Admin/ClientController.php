<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use App\Services\Google\GoogleAgencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function create(GoogleAgencyService $agency): View
    {
        return view('admin.clients.create', ['automaticReady' => $agency->canRunAutomaticFlow()]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = GbpClient::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $client->logEvent('client.created', ActorType::Admin, 'Client added by '.$request->user()->name.'.');

        $link = GbpOnboardingLink::issue($client, $request->user());
        $client->tryTransitionTo(ClientStatus::LinkSent);
        $client->logEvent('link.generated', ActorType::Admin, 'Onboarding link generated.');

        return redirect()->route('admin.clients.show', $client)
            ->with('status', 'Client added. Copy the link below and send it to them.')
            ->with('highlight_link', $link->url());
    }

    public function show(GbpClient $client, GoogleAgencyService $agency): View
    {
        $client->load(['latestAccessRequest', 'creator']);
        $link = $client->activeLink();

        return view('admin.clients.show', [
            'client' => $client,
            'link' => $link,
            'linkUrl' => $link?->isUsable() ? $link->url() : null,
            'logs' => $client->logs()->limit(25)->get(),
            'agencySettings' => $agency->settings(),
            'automaticReady' => $agency->canRunAutomaticFlow(),
            'agencyConnected' => $agency->connection() !== null,
            'clientGoogleActive' => $client->googleConnections()
                ->where('connection_type', 'CLIENT')
                ->where('status', ConnectionStatus::Active->value)
                ->exists(),
            'businessProfileManagerUrl' => config('gbp.business_profile_manager_url'),
        ]);
    }

    public function edit(GbpClient $client): View
    {
        return view('admin.clients.edit', ['client' => $client]);
    }

    public function update(ClientRequest $request, GbpClient $client): RedirectResponse
    {
        $client->update($request->safe()->except('access_method'));
        $client->logEvent('client.updated', ActorType::Admin, 'Client details edited by '.$request->user()->name.'.');

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client details saved.');
    }
}
