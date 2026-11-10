@extends('layouts.admin')

@section('title', 'Invitations')

@section('content')
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Invitations</h1>
        <p class="mt-1 text-sm text-ink-soft">Clients who invited the FTS Location Group appear here. Approve them to receive Manager access.</p>
    </div>

    @if ($problem)
        <div role="alert" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span>{{ $problem }}</span>
            <a href="{{ route('admin.settings.edit') }}" class="font-semibold underline underline-offset-4">Open Settings</a>
        </div>
    @endif

    <section class="card mb-8" aria-labelledby="waiting-heading">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
            <h2 id="waiting-heading" class="text-lg font-bold">Waiting for your approval</h2>
            @if ($locationGroup)<span class="font-mono text-xs text-ink-soft">{{ $locationGroup }}</span>@endif
        </div>

        @if ($invitations->isEmpty())
            <div class="grid place-items-center px-6 py-14 text-center">
                <svg width="48" height="48" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                    <path d="M32 6c-10 0-18 7.8-18 17.5C14 37 32 58 32 58s18-21 18-34.5C50 13.8 42 6 32 6Z" fill="var(--color-pin-tint)" stroke="var(--color-pin)" stroke-width="2.500"/>
                    <circle cx="32" cy="23.500" r="6.500" fill="var(--color-pin)"/>
                </svg>
                <h3 class="mt-4 text-lg font-bold">{{ $problem ? 'Invitations can\'t be loaded yet' : 'No invitations are waiting' }}</h3>
                <p class="mt-1 max-w-sm text-sm text-ink-soft">
                    {{ $problem ? 'Fix the message above and this list fills in.' : 'When a client authorizes the request, their business shows up here within moments.' }}
                </p>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($invitations as $invitation)
                    <li class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold">{{ $invitation['location'] ?: 'Unnamed business' }}</p>
                            <p class="truncate text-sm text-ink-soft">{{ $invitation['address'] ?: 'No address shown' }}</p>
                            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-soft">
                                <span class="badge bg-mist text-ink-soft ring-1 ring-inset ring-line">{{ ucfirst(strtolower(str_replace('_', ' ', $invitation['role'] ?? 'MANAGER'))) }} access</span>
                                @if ($invitation['client'])
                                    <a href="{{ route('admin.clients.show', $invitation['client']) }}" class="btn-link">Client: {{ $invitation['client']->business_name }}</a>
                                    <x-status-badge :status="$invitation['client']->access_status" />
                                @else
                                    <span>No matching client in this dashboard</span>
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.invitations.accept') }}" data-loading class="shrink-0"
                              data-confirm="The FTS Location Group will receive {{ ucfirst(strtolower($invitation['role'] ?? 'Manager')) }} access to {{ $invitation['location'] }}." data-confirm-title="Approve this invitation?" data-confirm-ok="Approve">
                            @csrf
                            <input type="hidden" name="invitation" value="{{ $invitation['name'] }}">
                            <button type="submit" class="btn-primary w-full sm:w-auto"><span data-label>Approve</span></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card" aria-labelledby="sent-heading">
        <div class="border-b border-line px-5 py-4">
            <h2 id="sent-heading" class="text-lg font-bold">Requests sent by clients</h2>
            <p class="mt-1 text-sm text-ink-soft">Clients whose request is submitted and not yet confirmed.</p>
        </div>

        @if ($waitingClients->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-soft">Nothing is waiting. Confirmed clients are on the Clients tab.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($waitingClients as $client)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.clients.show', $client) }}" class="font-semibold hover:text-pine">{{ $client->google_location_title ?: $client->business_name }}</a>
                            <p class="truncate text-xs text-ink-soft">{{ $client->google_location_address ?: $client->client_email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-status-badge :status="$client->access_status" />
                            <form method="POST" action="{{ route('admin.clients.refresh', $client) }}" data-loading>@csrf
                                <button class="btn-secondary" type="submit">Refresh status</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
