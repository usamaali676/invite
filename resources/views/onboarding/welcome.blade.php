@extends('layouts.onboarding')

@php
    $firstName = \Illuminate\Support\Str::of((string) $client->client_name)->trim()->before(' ')->toString();
    $contact = config('gbp.support_email');
@endphp

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="eyebrow">For {{ $client->business_name }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">Connect Your Google Business Profile</h1>

        <p class="mt-5 text-lg font-semibold">{{ $firstName !== '' ? 'Hi '.$firstName.',' : 'Hello,' }}</p>
        <p class="mt-2 leading-relaxed text-ink-soft">
            We are ready to start managing your Google Business Profile. To proceed, please connect your Google account and authorize {{ $agency }} to request Manager access to your business listing.
        </p>
        <p class="mt-3 text-sm text-ink-soft">This link was sent to you personally by {{ $agency }}. It takes about 2 minutes.</p>

        @if ($denied)
            <div role="status" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                No problem. Nothing was shared with us because the permission wasn't given. Press the button when you're ready to try again.
            </div>
        @endif

        @if ($problem)
            <div role="alert" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                We couldn't complete the connection. Your business profile has not been changed. Please contact our team for assistance.
            </div>
        @endif

        @if ($available)
            <form method="POST" action="{{ route('onboarding.start', ['token' => $token]) }}" data-loading class="mt-7">
                @csrf
                <x-google-button>Continue with Google</x-google-button>
                <p class="mt-3 text-center text-xs text-ink-soft">You'll go to Google's own page to sign in, then come straight back here.</p>
            </form>
        @else
            <div class="mt-7 rounded-xl border border-line bg-mist px-4 py-4 text-sm text-ink-soft">
                The connection isn't open just yet. Please contact {{ $agency }} and we'll finish it with you.
                <a class="mt-3 block font-semibold text-pine underline" href="mailto:{{ $contact }}">Email {{ config('gbp.agency_short_name') }}</a>
            </div>
        @endif
    </div>

    <section class="card mt-5 p-6 sm:p-8" aria-labelledby="safe-heading">
        <h2 id="safe-heading" class="text-xl font-bold">Your account stays safe</h2>
        <ul class="mt-4 space-y-4">
            @foreach ([
                ['Your Google password is never shared with '.config('gbp.agency_short_name').'.', 'You sign in on Google\'s own page. We only learn that you said yes.'],
                ['Your business ownership remains yours.', 'We ask for Manager access, never ownership.'],
                [config('gbp.agency_short_name').' is requesting Manager access to provide SEO services.', 'You can remove our access at any time in your Google Business Profile.'],
            ] as [$title, $detail])
                <li class="flex gap-3">
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-pine-tint text-pine" aria-hidden="true">
                        <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3.5 8.5 3 3 6-7"/></svg>
                    </span>
                    <span><span class="block font-semibold">{{ $title }}</span><span class="block text-sm text-ink-soft">{{ $detail }}</span></span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="card mt-5 p-6 sm:p-8" aria-labelledby="steps-heading">
        <h2 id="steps-heading" class="text-xl font-bold">What happens next</h2>
        <ol class="mt-4 space-y-5">
            @foreach ([
                ['Sign in with Google', 'Google shows its own sign-in and permission screen. Pick the account you use for your business.'],
                ['Choose your business', 'We show the listings in your account. Pick the right one.'],
                ['Confirm', 'Tick one box to confirm. That is the only thing we send to Google.'],
            ] as [$title, $detail])
                <li class="flex gap-4">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ink font-display text-sm font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span><span class="block font-semibold">{{ $title }}</span><span class="block text-sm leading-relaxed text-ink-soft">{{ $detail }}</span></span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="card mt-5 p-6 sm:p-8" aria-labelledby="access-heading">
        <h2 id="access-heading" class="text-xl font-bold">What Manager access means</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-pine-tint p-4">
                <p class="text-sm font-bold text-pine-deep">We can</p>
                <ul class="mt-2 space-y-1.5 text-sm text-pine-deep">
                    <li>Update your business information</li>
                    <li>Add photos and posts</li>
                    <li>Reply to your reviews</li>
                </ul>
            </div>
            <div class="rounded-2xl bg-mist p-4">
                <p class="text-sm font-bold">We can't</p>
                <ul class="mt-2 space-y-1.5 text-sm text-ink-soft">
                    <li>Become the owner of your profile</li>
                    <li>Remove you as the owner</li>
                    <li>Delete your profile</li>
                </ul>
            </div>
        </div>
    </section>

    @unless (config('gbp.google_app_verified'))
        <section class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-relaxed text-amber-950" aria-labelledby="notice-heading">
            <h2 id="notice-heading" class="font-bold">A note about a Google message</h2>
            <p class="mt-1">Google may show a screen saying it hasn't verified our app yet. We have applied for Google's verification and it's still in progress. The screen appears for any new app. If you'd rather not continue, tell us and we'll request access directly in Google, with no sign-in needed here.</p>
            <a href="mailto:{{ $contact }}?subject={{ rawurlencode('Request access directly for '.$client->business_name) }}" class="mt-3 inline-block font-semibold underline underline-offset-4">Ask us to do it directly</a>
        </section>
    @endunless

    <section class="card mt-5 px-6 sm:px-8" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="pb-1 pt-6 text-xl font-bold">Questions you may have</h2>
        <div>
            <x-faq :question="'Is this really from '.$agency.'?'">
                Yes. Your link was created for {{ $client->business_name }} by our team. You can check with us before you continue: email <a class="text-pine underline" href="mailto:{{ $contact }}">{{ $contact }}</a>@if (config('gbp.support_phone')) or call {{ config('gbp.support_phone') }}@endif.
            </x-faq>
            <x-faq question="Do you get my Google password?">
                No. You type your password only on Google's website. We never see it, and we never ask for it or for verification codes.
            </x-faq>
            <x-faq question="How do I remove your access later?">
                Open your Business Profile, go to <strong>People and access</strong>, and remove {{ $agency }}. It takes effect straight away.
            </x-faq>
            <x-faq question="What do you do with my information?">
                We use your business name and address only to send the access request to Google. We don't sell it. Read our <a class="text-pine underline" href="{{ route('legal.privacy') }}">privacy policy</a> for details.
            </x-faq>
        </div>
    </section>
@endsection
