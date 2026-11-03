@extends('layouts.onboarding')

@section('title', 'Link not available')

@section('content')
    <div class="card p-6 sm:p-8">
        <h1 class="text-2xl font-bold sm:text-3xl">This link isn't available</h1>
        <p class="mt-3 leading-relaxed text-ink-soft">
            It may have expired or been replaced by a newer one. Nothing has been changed on your Google Business Profile.
            Please ask {{ config('gbp.agency_name') }} to send you a fresh link.
        </p>
        <a href="mailto:{{ config('gbp.support_email') }}" class="btn-secondary mt-6">Contact {{ config('gbp.agency_short_name') }}</a>
    </div>
@endsection
