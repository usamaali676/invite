@extends('layouts.onboarding')

@section('title', 'Your connection')

@php
    $messages = [
        'submitted' => ['Request sent', 'Your request has been submitted successfully. '.$agency.' will now complete the required access confirmation.', 'ok'],
        'pending' => ['Waiting for approval', 'Your Google access request is awaiting approval. There is nothing else you need to do right now.', 'wait'],
        'connected' => ['You\'re connected', 'Your Google Business Profile is successfully connected with '.$agency.'.', 'ok'],
        'error' => ['We hit a problem', 'We couldn\'t complete the connection. Your business profile has not been changed. Please contact our team for assistance.', 'bad'],
    ];
    [$heading, $message, $tone] = $messages[$state];
@endphp

@section('content')
    <div class="card p-6 text-center sm:p-8">
        <div @class([
            'mx-auto grid size-16 place-items-center rounded-full',
            'bg-pine-tint text-pine' => $tone === 'ok',
            'bg-amber-100 text-amber-800' => $tone === 'wait',
            'bg-red-100 text-red-700' => $tone === 'bad',
        ]) aria-hidden="true">
            @if ($tone === 'ok')
                <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="2.500" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.500 4.500 4.500L19 7.500"/></svg>
            @elseif ($tone === 'wait')
                <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="2.500" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.500"/><path d="M12 7.500V12l3 2"/></svg>
            @else
                <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="2.500" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7.500v5.500M12 16.500v.01"/><circle cx="12" cy="12" r="8.500"/></svg>
            @endif
        </div>

        <h1 class="mt-5 text-3xl font-bold leading-tight">{{ $heading }}</h1>
        <p class="mx-auto mt-3 max-w-md leading-relaxed text-ink-soft">{{ $message }}</p>

        @if ($client->google_location_title && $state !== 'error')
            <div class="mx-auto mt-6 max-w-sm rounded-2xl bg-mist p-4 text-left">
                <p class="eyebrow">Business</p>
                <p class="mt-1 font-semibold">{{ $client->google_location_title }}</p>
                @if ($client->google_location_address)<p class="text-sm text-ink-soft">{{ $client->google_location_address }}</p>@endif
            </div>
        @endif

        @if ($state === 'error')
            <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="mailto:{{ config('gbp.support_email') }}" class="btn-primary">Contact {{ config('gbp.agency_short_name') }}</a>
            </div>
        @endif
    </div>
@endsection
