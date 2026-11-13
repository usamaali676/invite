@extends('layouts.onboarding')

@section('title', 'Terms of service')

@php
    $company = config('gbp.agency_name');
    $email = config('gbp.support_email');
@endphp

@section('content')
    <article class="card space-y-7 p-6 text-[0.95rem] leading-relaxed text-ink-soft sm:p-8">
        <header>
            <h1 class="text-3xl font-bold text-ink">Terms of service</h1>
            <p class="mt-2 text-sm">Last updated: November 14, 2026</p>
            <p class="mt-4">These terms cover the connection page that {{ $company }} sent you. Your SEO services are covered separately by the agreement you have with us.</p>
        </header>

        <section>
            <h2 class="text-xl font-bold text-ink">What this page does</h2>
            <p class="mt-2">It lets you ask Google to give {{ $company }} <strong class="text-ink">Manager</strong> access to one of your Business Profile listings, so we can provide the services you hired us for.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Your confirmation</h2>
            <p class="mt-2">Nothing is sent to Google until you tick the confirmation box and press the button. Please only do this if you are an owner of the listing, or you are allowed to give this access on the owner's behalf.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">What Manager access is, and is not</h2>
            <p class="mt-2">Manager access lets us work on your listing, for example update information, add photos and posts, and reply to reviews. It does not make us the owner. You remain the owner of your listing.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Ending the access</h2>
            <p class="mt-2">You can remove our access at any time in your Business Profile under <em>People and access</em>, and we will stop work on your listing when you do. You can also ask us by email.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Using the page properly</h2>
            <p class="mt-2">Your link is personal to you. Please don't share it. If you think someone else has your link, tell us and we will cancel it and send you a new one.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Availability</h2>
            <p class="mt-2">We aim to keep this page available, but it depends on Google's services and may sometimes be unavailable. If something fails, your Google Business Profile is left unchanged.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Your information</h2>
            <p class="mt-2">How we handle your information is described in our <a class="text-pine underline" href="{{ route('legal.privacy') }}">privacy policy</a>.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Contact</h2>
            <p class="mt-2">Questions about these terms: <a class="text-pine underline" href="mailto:{{ $email }}">{{ $email }}</a>.</p>
        </section>
    </article>
@endsection
