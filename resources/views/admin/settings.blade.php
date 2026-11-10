@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
    <h1 class="text-3xl font-bold">Agency settings</h1>
    <p class="mb-8 mt-1 text-sm text-ink-soft">Your Google Business Profile organization, and whether the connection to Google is ready.</p>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">

            <form method="POST" action="{{ route('admin.settings.update') }}" data-loading class="card space-y-5 p-6">
                @csrf
                @method('PUT')
                <h2 class="text-lg font-bold">Organization</h2>
                <x-field name="organization_name" label="Organization name" :value="$settings->organization_name" placeholder="Firm Tech Services" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="organization_id" label="Organization ID" :value="$settings->organization_id" placeholder="1234567890" hint="From Business Profile Manager, under your organization's settings." />
                    <x-field name="location_group_account_id" label="Location Group (business group) ID" :value="$settings->location_group_account_id" placeholder="9876543210" hint="The group that will manage client profiles. Not the same as the organization ID." />
                </div>
                <x-field name="manager_email" label="FTS manager email" type="email" :value="$settings->manager_email" hint="Used for reference only. Invitations always go to the Location Group, never to an email address." />

                <div>
                    <span class="field-label">Requested role</span>
                    <div class="field flex items-center justify-between bg-mist text-ink-soft"><span>Manager</span><span class="text-xs">Fixed. FTS never asks for ownership.</span></div>
                </div>

                <h2 class="!mt-8 text-lg font-bold">Google Cloud project</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="api_project_number" label="Project number" :value="$settings->api_project_number" placeholder="123456789012" />
                    <div>
                        <label for="api_approval_status" class="field-label">Business Profile API approval</label>
                        <select id="api_approval_status" name="api_approval_status" class="field">
                            @foreach ($approvalStatuses as $case)
                                <option value="{{ $case->value }}" @selected(old('api_approval_status', $settings->api_approval_status->value) === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        <p class="field-hint">Google doesn't tell us this. Set it to Approved once your project shows 300 QPM quota.</p>
                    </div>
                </div>
                <button class="btn-primary" type="submit"><span data-label>Save settings</span></button>
            </form>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6" aria-labelledby="ready-heading">
                <h2 id="ready-heading" class="text-lg font-bold">Is everything ready?</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($checks as $check)
                        <li class="flex gap-3 text-sm">
                            <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-xs font-bold {{ $check['ok'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900' }}" aria-hidden="true">{{ $check['ok'] ? '✓' : '!' }}</span>
                            <div>
                                <p class="font-semibold">{{ $check['label'] }}<span class="sr-only">: {{ $check['ok'] ? 'done' : 'needs attention' }}</span></p>
                                @unless ($check['ok'])<p class="text-xs text-ink-soft">{{ $check['hint'] }}</p>@endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 border-t border-line pt-4 text-xs text-ink-soft">Not ready? The manual agency request keeps working in the meantime.</p>
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-bold">FTS Google account</h2>
                @if ($connection)
                    <p class="mt-1 text-sm text-ink-soft">Connected{{ $connection->google_account_name ? ' as '.$connection->google_account_name : '' }}. Used to find your Location Group, approve invitations and confirm access.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.settings.test') }}" data-loading>@csrf
                            <button class="btn-secondary" type="submit">Test connection</button>
                        </form>
                        <form method="POST" action="{{ route('admin.settings.google.disconnect') }}" data-loading
                              data-confirm="The stored tokens are deleted and Google access is revoked." data-confirm-title="Disconnect FTS Google account?" data-confirm-ok="Disconnect">@csrf
                            <button class="btn-danger" type="submit">Disconnect</button>
                        </form>
                    </div>

                    @if ($detectError)
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $detectError }}</p>
                    @elseif ($detected)
                        <h3 class="mt-6 text-sm font-bold">Groups found in your Google account</h3>
                        <ul class="mt-2 divide-y divide-line text-sm">
                            @foreach ($detected as $account)
                                <li class="flex items-center justify-between gap-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">{{ $account['accountName'] }}</p>
                                        <p class="text-xs text-ink-soft">{{ str_replace('_', ' ', $account['type']) }} · ID <span class="font-mono">{{ $account['id'] }}</span></p>
                                    </div>
                                    @if ($account['type'] === 'LOCATION_GROUP')
                                        <button type="button" class="btn-secondary shrink-0" onclick="document.getElementById('location_group_account_id').value='{{ $account['id'] }}'; document.getElementById('location_group_account_id').focus();">Use as Location Group</button>
                                    @elseif ($account['type'] === 'ORGANIZATION')
                                        <button type="button" class="btn-secondary shrink-0" onclick="document.getElementById('organization_id').value='{{ $account['id'] }}'; document.getElementById('organization_name').value=@js($account['accountName']);">Use as Organization</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="field-hint">Choosing one fills the form. Press Save settings to keep it.</p>
                    @endif
                @else
                    <p class="mt-1 text-sm text-ink-soft">Optional. Sign in with the FTS Google account that manages your organization so FTS can find the Location Group, approve invitations and verify access.</p>
                    @if ($oauthConfigured)
                        <a href="{{ route('admin.settings.google.connect') }}" class="btn-primary mt-4">Connect FTS Google account</a>
                    @else
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to your .env file first.</p>
                    @endif
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-bold">Google Cloud values</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div>
                        <dt class="eyebrow">Authorized redirect URI</dt>
                        <dd class="mt-1 flex gap-2">
                            <input id="redirect-uri" readonly value="{{ $redirectUri }}" class="field font-mono text-xs" aria-label="Authorized redirect URI">
                            <button type="button" class="btn-secondary shrink-0" data-copy="#redirect-uri"><span data-copy-label>Copy</span></button>
                        </dd>
                    </div>
                    <div>
                        <dt class="eyebrow">Scope requested</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ config('gbp.google.scope') }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
@endsection
