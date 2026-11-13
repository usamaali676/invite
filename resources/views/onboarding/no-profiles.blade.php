@extends('layouts.onboarding')

@section('title', 'No business found')

@section('content')
    <div class="card p-6 sm:p-8">
        <h1 class="text-3xl font-bold leading-tight">We couldn't find a business listing</h1>
        <p class="mt-3 leading-relaxed text-ink-soft">
            The Google account you signed in with doesn't manage any business listings. This often happens when a different Google account owns the listing.
            Nothing on your Google Business Profile has been changed.
        </p>
        @include('onboarding.partials.signed-in')
        <ul class="mt-5 list-disc space-y-1.5 pl-5 text-sm text-ink-soft">
            <li>Try again and choose the Google account you use for your business.</li>
            <li>Or contact us and we'll help you find the right account.</li>
        </ul>
        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <form method="POST" action="{{ route('onboarding.start', ['token' => $token]) }}" data-loading class="flex-1">
                @csrf
                <button class="btn-primary w-full" type="submit"><span data-label>Try a different Google account</span></button>
            </form>
            <a href="mailto:{{ config('gbp.support_email') }}" class="btn-secondary flex-1">Contact {{ config('gbp.agency_short_name') }}</a>
        </div>
    </div>
@endsection
