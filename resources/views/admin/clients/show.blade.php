@extends('layouts.admin')

@section('title', $client->business_name)

@php
    use App\Enums\AccessMethod;
    use App\Enums\AccessRequestStatus;
    use App\Enums\ClientStatus;

    $request = $client->latestAccessRequest;
    $isManual = $client->access_method === AccessMethod::AgencyManual;
    $submittedStage = in_array($client->access_status, [ClientStatus::RequestSubmitted, ClientStatus::PendingApproval, ClientStatus::Connected], true);
    $highlight = session('highlight_link');
@endphp

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="btn-link">&larr; All clients</a>

    <div class="mb-8 mt-3 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ $client->business_name }}</h1>
            <p class="mt-1 text-sm text-ink-soft">
                {{ collect([$client->client_name, $client->client_email])->filter()->implode(' · ') ?: 'No contact details yet' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <x-status-badge :status="$client->access_status" class="!px-3 !py-1.5 !text-sm" />
            <a href="{{ route('admin.clients.edit', $client) }}" class="btn-secondary">Edit</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Onboarding link --}}
            <section class="card p-6" aria-labelledby="link-heading">
                <h2 id="link-heading" class="text-lg font-bold">Onboarding link</h2>

                @if ($linkUrl)
                    <p class="mt-1 text-sm text-ink-soft">
                        Send this to the client by WhatsApp, email or any other channel. It expires {{ $link->expires_at->diffForHumans() }} ({{ $link->expires_at->format('M j, Y') }}).
                    </p>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <input id="onboarding-url" type="text" readonly value="{{ $linkUrl }}" onclick="this.select()"
                               class="field font-mono text-xs {{ $highlight ? 'ring-2 ring-pine/40' : '' }}" aria-label="Onboarding link">
                        <button type="button" class="btn-primary shrink-0" data-copy="#onboarding-url"><span data-copy-label>Copy link</span></button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($client->client_email)
                            <form method="POST" action="{{ route('admin.clients.link.email', $client) }}" data-loading>@csrf
                                <button class="btn-secondary" type="submit">Email to {{ $client->client_email }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.clients.link.store', $client) }}" data-loading
                              data-confirm="The current link will stop working immediately. A new one will be created." data-confirm-title="Regenerate link?" data-confirm-ok="Regenerate">@csrf
                            <button class="btn-secondary" type="submit">Regenerate link</button>
                        </form>
                        <form method="POST" action="{{ route('admin.clients.link.destroy', $client) }}" data-loading
                              data-confirm="The client will no longer be able to open their link." data-confirm-title="Revoke link?" data-confirm-ok="Revoke link">@csrf @method('DELETE')
                            <button class="btn-danger" type="submit">Revoke link</button>
                        </form>
                    </div>
                @else
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $link ? 'The previous link is no longer valid.' : 'No link has been created yet.' }}
                        Create a new one to send to the client.
                    </p>
                    <form method="POST" action="{{ route('admin.clients.link.store', $client) }}" data-loading class="mt-4">@csrf
                        <button class="btn-primary" type="submit">Generate onboarding link</button>
                    </form>
                @endif
            </section>

            {{-- Access status --}}
            <section class="card p-6" id="access" aria-labelledby="access-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="access-heading" class="text-lg font-bold">Access status</h2>
                    <span class="text-xs font-semibold text-ink-soft">{{ $client->access_method->label() }}</span>
                </div>

                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="eyebrow">Google profile</dt>
                        <dd class="mt-1 font-medium">{{ $client->google_location_title ?: 'Not selected yet' }}</dd>
                        @if ($client->google_location_address)<dd class="text-xs text-ink-soft">{{ $client->google_location_address }}</dd>@endif
                    </div>
                    <div>
                        <dt class="eyebrow">Requested role</dt>
                        <dd class="mt-1 font-medium">Manager</dd>
                    </div>
                    @if ($request)
                        <div>
                            <dt class="eyebrow">Request</dt>
                            <dd class="mt-1 font-medium">{{ str_replace('_', ' ', $request->status->value) }}</dd>
                            <dd class="text-xs text-ink-soft">Sent {{ $request->submitted_at?->diffForHumans() ?? 'not yet' }}</dd>
                        </div>
                        <div>
                            <dt class="eyebrow">Last checked with Google</dt>
                            <dd class="mt-1 font-medium">{{ $request->last_checked_at?->diffForHumans() ?? 'Not checked yet' }}</dd>
                        </div>
                        @if ($request->location_resource_name)
                            <div>
                                <dt class="eyebrow">Location</dt>
                                <dd class="mt-1 font-mono text-xs">{{ $request->location_resource_name }}</dd>
                            </div>
                        @endif
                        @if ($request->invitation_resource_name)
                            <div>
                                <dt class="eyebrow">Invitation</dt>
                                <dd class="mt-1 break-all font-mono text-xs">{{ $request->invitation_resource_name }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                @if ($request?->error_message)
                    <div class="mt-5 rounded-xl border {{ $request->status === AccessRequestStatus::Failed ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} px-4 py-3 text-sm">
                        {{ $request->error_message }}
                    </div>
                @endif

                @if ($request?->status === AccessRequestStatus::PendingApproval && ! $isManual)
                    <div class="mt-5 rounded-xl border border-line bg-mist px-4 py-3 text-sm text-ink-soft">
                        <strong class="text-ink">What happens next:</strong> Google has sent the invitation to the FTS Location Group.
                        It becomes active once someone approves it in Business Profile Manager
                        (Manage invites &rarr; Approve){{ $agencyConnected ? ', or from the Invitations tab' : '' }}.
                        Then press Refresh status.
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($request && $request->status->isOpen())
                        <form method="POST" action="{{ route('admin.clients.refresh', $client) }}" data-loading>@csrf
                            <button class="btn-primary" type="submit">Refresh status</button>
                        </form>
                    @endif
                    @if ($request?->status === AccessRequestStatus::Failed && ! $isManual)
                        <form method="POST" action="{{ route('admin.clients.retry', $client) }}" data-loading>@csrf
                            <button class="btn-primary" type="submit">Retry failed request</button>
                        </form>
                    @endif
                    @if ($clientGoogleActive)
                        <form method="POST" action="{{ route('admin.clients.disconnect', $client) }}" data-loading
                              data-confirm="This revokes the client's Google authorization and deletes the stored tokens. Retrying later will need a new link." data-confirm-title="Revoke client's Google access?" data-confirm-ok="Revoke">@csrf
                            <button class="btn-secondary" type="submit">Revoke client's Google authorization</button>
                        </form>
                    @endif
                </div>

                @if (! $submittedStage)
                    <form method="POST" action="{{ route('admin.clients.method', $client) }}" data-loading class="mt-6 border-t border-line pt-5">
                        @csrf
                        <input type="hidden" name="access_method" value="{{ $isManual ? 'API' : 'AGENCY_MANUAL' }}">
                        <p class="text-sm text-ink-soft">
                            {{ $isManual ? 'Prefer the client to sign in with Google instead?' : 'Google API access not ready, or the client has trouble signing in?' }}
                            <button class="btn-link" type="submit">Switch to {{ $isManual ? 'Google sign-in (automatic)' : 'agency request (manual)' }}</button>
                        </p>
                    </form>
                @endif
            </section>

            {{-- Manual agency workflow --}}
            @if ($isManual)
                <section class="card p-6" aria-labelledby="manual-heading">
                    <h2 id="manual-heading" class="text-lg font-bold">Agency access request</h2>
                    <p class="mt-1 text-sm text-ink-soft">Google has no public API for starting this request, so it is sent by hand in Business Profile Manager.</p>

                    <dl class="mt-5 grid gap-4 rounded-xl bg-mist p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="eyebrow">Business to request</dt>
                            <dd class="mt-1 font-semibold">{{ $client->business_name }}</dd>
                            @if ($client->google_maps_url)<dd><a href="{{ $client->google_maps_url }}" target="_blank" rel="noopener noreferrer" class="btn-link">Open in Google Maps</a></dd>@endif
                        </div>
                        <div>
                            <dt class="eyebrow">Add it to this FTS business group</dt>
                            <dd class="mt-1 font-semibold">{{ $agencySettings->organization_name ?: config('gbp.agency_name') }}</dd>
                            <dd class="font-mono text-xs text-ink-soft">
                                @if ($agencySettings->hasLocationGroup()) Location Group ID {{ $agencySettings->location_group_account_id }}
                                @else <a href="{{ route('admin.settings.edit') }}" class="text-red-700 underline">Location Group not set (open Settings)</a>@endif
                            </dd>
                        </div>
                    </dl>

                    <ol class="mt-5 list-decimal space-y-1.5 pl-5 text-sm text-ink-soft">
                        <li>Open Business Profile Manager and choose the FTS organization.</li>
                        <li>Click <strong class="text-ink">Manage invites</strong>, then <strong class="text-ink">Request access</strong>.</li>
                        <li>Search for the business, pick the right profile, and choose the business group shown above.</li>
                        <li>Send the request, then come back here and record it.</li>
                    </ol>
                    <a href="{{ $businessProfileManagerUrl }}" target="_blank" rel="noopener noreferrer" class="btn-secondary mt-4">Open Business Profile Manager</a>

                    @if (! $submittedStage)
                        <form method="POST" action="{{ route('admin.clients.manual.submitted', $client) }}" data-loading class="mt-6 space-y-3 border-t border-line pt-5">
                            @csrf
                            @if ($client->client_email && $linkUrl)
                                <label class="flex items-start gap-2 text-sm text-ink-soft">
                                    <input type="checkbox" name="email_client" value="1" checked class="mt-0.5 size-4 rounded border-line"> Email the client the steps to approve it ({{ $client->client_email }})
                                </label>
                            @endif
                            <button class="btn-primary" type="submit">I've sent the request</button>
                        </form>
                    @endif

                    @if ($client->access_status === ClientStatus::PendingApproval || $client->access_status === ClientStatus::RequestSubmitted)
                        <form method="POST" action="{{ route('admin.clients.manual.connected', $client) }}" data-loading class="mt-6 space-y-3 border-t border-line pt-5">
                            @csrf
                            <h3 class="text-sm font-bold">Waiting for the client to approve</h3>
                            <p class="text-sm text-ink-soft">
                                Mark as connected only once FTS truly has access.
                                {{ $agencyConnected ? 'Google is checked first.' : 'Connect the FTS Google account in Settings to let Google confirm this for you.' }}
                            </p>
                            <label class="flex items-start gap-2 text-sm text-ink-soft">
                                <input type="checkbox" name="confirmed" value="1" class="mt-0.5 size-4 rounded border-line"> I opened Business Profile Manager and FTS now has Manager access to this profile.
                            </label>
                            <button class="btn-primary" type="submit">Check and mark connected</button>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h2 class="text-lg font-bold">Details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="eyebrow">Added</dt><dd class="mt-1">{{ $client->created_at->format('M j, Y') }}{{ $client->creator ? ' by '.$client->creator->name : '' }}</dd></div>
                    @if ($client->google_maps_url)
                        <div><dt class="eyebrow">Google Maps</dt><dd class="mt-1"><a class="btn-link" target="_blank" rel="noopener noreferrer" href="{{ $client->google_maps_url }}">Open listing</a></dd></div>
                    @endif
                    <div><dt class="eyebrow">Internal notes</dt><dd class="mt-1 whitespace-pre-line text-ink-soft">{{ $client->internal_notes ?: 'None' }}</dd></div>
                </dl>
            </section>

            <section class="card p-6" aria-labelledby="activity-heading">
                <h2 id="activity-heading" class="text-lg font-bold">Activity</h2>
                @if ($logs->isEmpty())
                    <p class="mt-2 text-sm text-ink-soft">Nothing has happened yet.</p>
                @else
                    <ol class="mt-4 space-y-4 border-l-2 border-line pl-4">
                        @foreach ($logs as $log)
                            <li class="relative text-sm">
                                <span class="absolute -left-[1.4rem] top-1.5 size-2.5 rounded-full {{ $log->actor_type->value === 'CLIENT' ? 'bg-pin' : ($log->actor_type->value === 'ADMIN' ? 'bg-pine' : 'bg-ink-faint') }}"></span>
                                <p class="font-medium">{{ $log->description ?: $log->action }}</p>
                                <p class="text-xs text-ink-soft">{{ ucfirst(strtolower($log->actor_type->value)) }} · {{ $log->created_at->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
@endsection
