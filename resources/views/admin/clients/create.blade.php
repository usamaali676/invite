@extends('layouts.admin')

@section('title', 'Add client')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="btn-link">&larr; All clients</a>
    <h1 class="mb-1 mt-3 text-3xl font-bold">Add a client</h1>
    <p class="mb-8 text-sm text-ink-soft">Only the business name is required. A link is created as soon as you save.</p>

    <form method="POST" action="{{ route('admin.clients.store') }}" data-loading class="card max-w-2xl space-y-5 p-6">
        @csrf
        <x-field name="business_name" label="Business name" required placeholder="Maple Street Dental" autofocus />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="client_name" label="Client name" placeholder="Dana Whitfield" />
            <x-field name="client_email" label="Client email" type="email" placeholder="dana@maplestreetdental.com" />
        </div>
        <x-field name="google_maps_url" label="Google Maps URL" type="url" placeholder="https://maps.app.goo.gl/…" hint="Helps you spot the right listing. The system finds the technical IDs itself." />

        <fieldset>
            <legend class="field-label">How should access be requested?</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-xl border border-line p-4 has-[:checked]:border-pine has-[:checked]:bg-pine-tint">
                    <input type="radio" name="access_method" value="API" class="mt-1" @checked(old('access_method', $automaticReady ? 'API' : 'AGENCY_MANUAL') === 'API')>
                    <span>
                        <span class="block text-sm font-semibold">Google sign-in (automatic)</span>
                        <span class="block text-xs text-ink-soft">The client signs in with Google and approves the request themselves.</span>
                        @unless ($automaticReady)<span class="mt-1 block text-xs font-medium text-amber-800">Not ready yet. Finish setup first.</span>@endunless
                    </span>
                </label>
                <label class="flex cursor-pointer gap-3 rounded-xl border border-line p-4 has-[:checked]:border-pine has-[:checked]:bg-pine-tint">
                    <input type="radio" name="access_method" value="AGENCY_MANUAL" class="mt-1" @checked(old('access_method', $automaticReady ? 'API' : 'AGENCY_MANUAL') === 'AGENCY_MANUAL')>
                    <span>
                        <span class="block text-sm font-semibold">Agency request (manual)</span>
                        <span class="block text-xs text-ink-soft">You send the request in Business Profile Manager. The client gets approval steps.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <x-field name="internal_notes" label="Internal notes" type="textarea" hint="Only your team sees this." />

        <div class="flex items-center gap-3 pt-2">
            <button class="btn-primary" type="submit"><span data-label>Save and create link</span></button>
            <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
