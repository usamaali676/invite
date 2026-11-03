@extends('layouts.onboarding')

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="eyebrow">For {{ $client->business_name }}</p>
        <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">Connect Your Google Business Profile</h1>
        <p class="mt-4 leading-relaxed text-ink-soft">
            We are ready to start managing your Google Business Profile. To proceed, please connect your Google account and authorize {{ $agency }} to request Manager access to your business listing.
        </p>

        <ul class="mt-6 space-y-4">
            @foreach ([
                ['Your Google password is never shared with '.config('gbp.agency_short_name').'.', 'Google asks you to sign in on its own page.'],
                ['Your business ownership remains yours.', 'We ask for Manager access, never ownership.'],
                [config('gbp.agency_short_name').' is requesting Manager access to provide SEO services.', 'You can remove it any time in your Google Business Profile.'],
            ] as [$title, $detail])
                <li class="flex gap-3">
                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-pine-tint text-pine" aria-hidden="true">
                        <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.500" stroke-linecap="round" stroke-linejoin="round"><path d="m3.500 8.500 3 3 6-7"/></svg>
                    </span>
                    <span><span class="block font-semibold">{{ $title }}</span><span class="block text-sm text-ink-soft">{{ $detail }}</span></span>
                </li>
            @endforeach
        </ul>

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
            <form method="POST" action="{{ route('onboarding.start', ['token' => $token]) }}" data-loading class="mt-8">
                @csrf
                <button type="submit" class="btn-primary btn-lg w-full"><span data-label>Continue with Google</span></button>
                <p class="mt-3 text-center text-xs text-ink-soft">You'll go to Google's secure page, then come straight back.</p>
            </form>
        @else
            <div class="mt-8 rounded-xl border border-line bg-mist px-4 py-4 text-sm text-ink-soft">
                The connection isn't open just yet. Please contact {{ $agency }} and we'll finish it with you.
                <a class="mt-3 block font-semibold text-pine underline" href="mailto:{{ config('gbp.support_email') }}">Email {{ config('gbp.agency_short_name') }}</a>
            </div>
        @endif
    </div>
@endsection
