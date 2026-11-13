@extends('layouts.onboarding')

@section('title', 'Link not available')

@section('content')
    <div class="card p-6 sm:p-8">
        <h1 class="text-2xl font-bold sm:text-3xl">This link isn't working</h1>
        <p class="mt-3 leading-relaxed text-ink-soft">
            Links stop working after a while, or when we send a newer one. Nothing has been changed on your Google Business Profile.
            Please ask {{ config('gbp.agency_name') }} to send you a fresh link. It only takes a moment.
        </p>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="mailto:{{ config('gbp.support_email') }}" class="btn-primary">Email {{ config('gbp.agency_short_name') }}</a>
            @if (config('gbp.support_phone'))
                <a href="tel:{{ preg_replace('/[^+\d]/', '', config('gbp.support_phone')) }}" class="btn-secondary">Call {{ config('gbp.support_phone') }}</a>
            @endif
        </div>
    </div>
@endsection
