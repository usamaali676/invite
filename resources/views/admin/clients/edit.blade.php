@extends('layouts.admin')

@section('title', 'Edit '.$client->business_name)

@section('content')
    <a href="{{ route('admin.clients.show', $client) }}" class="btn-link">&larr; {{ $client->business_name }}</a>
    <h1 class="mb-8 mt-3 text-3xl font-bold">Edit client</h1>

    <form method="POST" action="{{ route('admin.clients.update', $client) }}" data-loading class="card max-w-2xl space-y-5 p-6">
        @csrf
        @method('PUT')
        <x-field name="business_name" label="Business name" :value="$client->business_name" required />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="client_name" label="Client name" :value="$client->client_name" />
            <x-field name="client_email" label="Client email" type="email" :value="$client->client_email" />
        </div>
        <x-field name="google_maps_url" label="Google Maps URL" type="url" :value="$client->google_maps_url" />
        <x-field name="internal_notes" label="Internal notes" type="textarea" :value="$client->internal_notes" hint="Only your team sees this." />
        <div class="flex items-center gap-3 pt-2">
            <button class="btn-primary" type="submit"><span data-label>Save changes</span></button>
            <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
@endsection
