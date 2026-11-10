@extends('layouts.admin')

@section('title', 'Clients')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">Clients</h1>
            <p class="mt-1 text-sm text-ink-soft">Add a client, send the link, and watch the profile connect.</p>
        </div>
        <a href="{{ route('admin.clients.create') }}" class="btn-primary">Add client</a>
    </div>

    @unless ($automaticReady)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span>Automatic Google sign-in isn't ready yet. Clients can still be handled with the manual agency request.</span>
            <a href="{{ route('admin.settings.edit') }}" class="font-semibold underline underline-offset-4">Finish setup</a>
        </div>
    @endunless

    <dl class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Total clients', $stats['total'], 'bg-ink'],
            ['Connected profiles', $stats['connected'], 'bg-pine'],
            ['Pending requests', $stats['pending'], 'bg-amber-500'],
            ['Failed requests', $stats['failed'], 'bg-red-500'],
        ] as [$label, $value, $dot])
            <div class="card p-4">
                <dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-ink-soft"><span class="size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $label }}</dt>
                <dd class="mt-2 font-display text-3xl font-bold">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-4 flex flex-wrap gap-2" data-no-loading>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search business, name or email" class="field max-w-xs" aria-label="Search clients">
        <select name="status" class="field w-auto" aria-label="Filter by status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach (\App\Enums\ClientStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($statusFilter === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Search</button>
        @if ($search !== '' || $statusFilter)
            <a href="{{ route('admin.dashboard') }}" class="btn-link self-center">Clear</a>
        @endif
    </form>

    @if ($clients->isEmpty())
        <div class="card grid place-items-center px-6 py-16 text-center">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <path d="M32 6c-10 0-18 7.8-18 17.5C14 37 32 58 32 58s18-21 18-34.5C50 13.8 42 6 32 6Z" fill="var(--color-pin-tint)" stroke="var(--color-pin)" stroke-width="2.5"/>
                <circle cx="32" cy="23.5" r="6.5" fill="var(--color-pin)"/>
            </svg>
            @if ($search !== '' || $statusFilter)
                <h2 class="mt-5 text-xl font-bold">No clients match that search</h2>
                <p class="mt-1 max-w-sm text-sm text-ink-soft">Try a different name or clear the filters.</p>
            @else
                <h2 class="mt-5 text-xl font-bold">No clients yet</h2>
                <p class="mt-1 max-w-sm text-sm text-ink-soft">Add a business, copy its link and send it. You'll see it here as soon as the client opens it.</p>
                <a href="{{ route('admin.clients.create') }}" class="btn-primary mt-6">Add your first client</a>
            @endif
        </div>
    @else
        <div class="card">
            <div>
                <table class="block w-full text-left text-sm xl:table">
                    <thead class="hidden border-b border-line bg-mist text-xs font-semibold uppercase tracking-wide text-ink-soft xl:table-header-group">
                        <tr>
                            <th class="px-4 py-3">Business</th>
                            <th class="px-4 py-3">Client email</th>
                            <th class="px-4 py-3">Google profile</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="block divide-y divide-line xl:table-row-group">
                        @foreach ($clients as $client)
                            @php
                                $link = $client->onboardingLinks->first();
                                $linkUrl = $link?->isUsable() ? $link->url() : null;
                                $failedRequest = $client->latestAccessRequest?->status === \App\Enums\AccessRequestStatus::Failed && $client->access_method === \App\Enums\AccessMethod::Api;
                            @endphp
                            <tr class="block space-y-3 p-4 align-top xl:table-row xl:space-y-0 xl:p-0">
                                <td class="block xl:table-cell xl:px-4 xl:py-3">
                                    <a href="{{ route('admin.clients.show', $client) }}" class="font-semibold hover:text-pine">{{ $client->business_name }}</a>
                                    @if ($client->client_name)<div class="text-xs text-ink-soft">{{ $client->client_name }}</div>@endif
                                </td>
                                <td class="block text-ink-soft xl:table-cell xl:px-4 xl:py-3"><span class="block max-w-[13rem] truncate" title="{{ $client->client_email }}">{{ $client->client_email ?: '—' }}</span></td>
                                <td class="block xl:table-cell xl:px-4 xl:py-3">
                                    @if ($client->google_location_title)
                                        <div class="max-w-[15rem] truncate font-medium" title="{{ $client->google_location_title }}">{{ $client->google_location_title }}</div>
                                        <div class="max-w-[15rem] truncate text-xs text-ink-soft" title="{{ $client->google_location_address }}">{{ $client->google_location_address }}</div>
                                    @elseif ($client->google_maps_url)
                                        <a href="{{ $client->google_maps_url }}" target="_blank" rel="noopener noreferrer" class="btn-link">Open in Maps</a>
                                    @else
                                        <span class="text-ink-faint">Not selected yet</span>
                                    @endif
                                </td>
                                <td class="hidden text-ink-soft xl:table-cell xl:px-4 xl:py-3">{{ $client->access_method->shortLabel() }}</td>
                                <td class="block xl:table-cell xl:px-4 xl:py-3"><x-status-badge :status="$client->access_status" /><span class="ml-2 text-xs text-ink-soft xl:hidden">{{ $client->access_method->shortLabel() }} · {{ $client->created_at->format('M j') }}</span></td>
                                <td class="hidden whitespace-nowrap text-ink-soft xl:table-cell xl:px-4 xl:py-3">{{ $client->created_at->format('M j, Y') }}</td>
                                <td class="block xl:table-cell xl:px-4 xl:py-3">
                                    <div class="flex items-center gap-2 xl:justify-end">
                                        @if ($linkUrl)
                                            <input type="hidden" id="link-{{ $client->id }}" value="{{ $linkUrl }}">
                                            <button type="button" class="btn-secondary" data-copy="#link-{{ $client->id }}"><span data-copy-label>Copy link</span></button>
                                        @else
                                            <form method="POST" action="{{ route('admin.clients.link.store', $client) }}" data-loading>
                                                @csrf
                                                <button class="btn-secondary" type="submit">Generate link</button>
                                            </form>
                                        @endif
                                        <details class="relative">
                                            <summary class="btn-secondary cursor-pointer list-none" aria-label="More actions for {{ $client->business_name }}">More</summary>
                                            <div class="absolute right-0 z-10 mt-2 w-56 space-y-1 rounded-xl border border-line bg-paper p-2 text-left shadow-lg">
                                                <a href="{{ route('admin.clients.show', $client) }}" class="block rounded-lg px-3 py-2 text-sm font-medium hover:bg-mist">View client</a>
                                                <a href="{{ route('admin.clients.edit', $client) }}" class="block rounded-lg px-3 py-2 text-sm font-medium hover:bg-mist">Edit details</a>
                                                <a href="{{ route('admin.clients.show', $client) }}#access" class="block rounded-lg px-3 py-2 text-sm font-medium hover:bg-mist">View access status</a>
                                                @if ($linkUrl && $client->client_email)
                                                    <form method="POST" action="{{ route('admin.clients.link.email', $client) }}" data-loading>@csrf
                                                        <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-mist">Send email</button>
                                                    </form>
                                                @endif
                                                @if ($client->latestAccessRequest?->status->isOpen())
                                                    <form method="POST" action="{{ route('admin.clients.refresh', $client) }}" data-loading>@csrf
                                                        <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-mist">Refresh status</button>
                                                    </form>
                                                @endif
                                                @if ($failedRequest)
                                                    <form method="POST" action="{{ route('admin.clients.retry', $client) }}" data-loading>@csrf
                                                        <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-mist">Retry failed request</button>
                                                    </form>
                                                @endif
                                                @if ($link)
                                                    <form method="POST" action="{{ route('admin.clients.link.store', $client) }}" data-confirm="The current link will stop working immediately. A new one will be created." data-confirm-title="Regenerate link?" data-confirm-ok="Regenerate" data-loading>@csrf
                                                        <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-mist">Regenerate link</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.clients.link.destroy', $client) }}" data-confirm="The client will no longer be able to open their link." data-confirm-title="Revoke link?" data-confirm-ok="Revoke link" data-loading>@csrf @method('DELETE')
                                                        <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">Revoke link</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
@endsection
