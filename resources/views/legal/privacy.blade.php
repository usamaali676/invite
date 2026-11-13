@extends('layouts.onboarding')

@section('title', 'Privacy policy')

@php
    $company = config('gbp.agency_name');
    $email = config('gbp.support_email');
@endphp

@section('content')
    <article class="card space-y-7 p-6 text-[0.95rem] leading-relaxed text-ink-soft sm:p-8">
        <header>
            <h1 class="text-3xl font-bold text-ink">Privacy policy</h1>
            <p class="mt-2 text-sm">Last updated: November 14, 2026</p>
            <p class="mt-4">{{ $company }} ("we", "us") helps businesses manage their Google Business Profile. This page explains what happens to your information when you use the connection link we sent you.</p>
        </header>

        <section>
            <h2 class="text-xl font-bold text-ink">Who we are</h2>
            <p class="mt-2">{{ $company }}@if (config('gbp.company_address')), {{ config('gbp.company_address') }}@endif. Contact: <a class="text-pine underline" href="mailto:{{ $email }}">{{ $email }}</a>.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">What we receive</h2>
            <ul class="mt-2 list-disc space-y-1.5 pl-5">
                <li><strong class="text-ink">From our team:</strong> your business name and, if we have them, your name and email, so we can send you the link.</li>
                <li><strong class="text-ink">From Google, only after you say yes:</strong> the name of your Google account, and the Business Profile listings you manage (their name and address), so you can pick the right one. We then ask Google to give {{ $company }} Manager access to that listing and check whether Google confirms it.</li>
                <li><strong class="text-ink">On our website:</strong> a session cookie that keeps your progress while you use the link, and basic server logs (such as your IP address) that we use for security and to prevent abuse. We use no advertising or analytics trackers on these pages.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">What we never receive</h2>
            <p class="mt-2">Your Google password or verification codes. You enter those only on Google's own pages. We do not ask for access to your email, contacts, files or any Google service other than Business Profile.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Why we use it</h2>
            <p class="mt-2">Only to send the Manager access request you approved, to confirm that it worked, and to support you if something goes wrong. We do not sell your information and we do not use it for advertising.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Google user data</h2>
            <p class="mt-2">The permission we ask Google for is <code class="rounded bg-mist px-1.5 py-0.5 text-xs text-ink">https://www.googleapis.com/auth/business.manage</code>. It is used solely to find your business listings and to request Manager access for {{ $company }}.</p>
            <p class="mt-2">{{ $company }}'s use and transfer of information received from Google APIs adheres to the <a class="text-pine underline" href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">How we protect it</h2>
            <p class="mt-2">Pages are served over an encrypted connection. The temporary Google access token we receive from you is stored encrypted, and it is revoked and deleted as soon as Google confirms our access, or earlier if you ask us. Your link is a long random code that expires and can be cancelled at any time.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Who we share it with</h2>
            <p class="mt-2">Only the service providers that run this site for us (for example web hosting and email delivery), and only as needed to provide it. We share information with authorities only if the law requires it.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">How long we keep it</h2>
            <p class="mt-2">We keep your business details and a short history of the request while we provide services to you. You can ask us to delete them at any time.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Your choices</h2>
            <ul class="mt-2 list-disc space-y-1.5 pl-5">
                <li><strong class="text-ink">Remove our access:</strong> open your Business Profile, go to <em>People and access</em>, and remove {{ $company }}.</li>
                <li><strong class="text-ink">Cancel what you shared with this app:</strong> visit <a class="text-pine underline" href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a> and remove this app.</li>
                <li><strong class="text-ink">Ask a question or request deletion:</strong> email <a class="text-pine underline" href="mailto:{{ $email }}">{{ $email }}</a>.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold text-ink">Changes</h2>
            <p class="mt-2">If we change this policy, we will update the date at the top of this page.</p>
        </section>
    </article>
@endsection
