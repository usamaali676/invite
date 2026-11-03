@extends('layouts.onboarding')

@section('title', 'Confirm access request')

@section('content')
    <div class="card p-6 sm:p-8">
        <h1 class="text-3xl font-bold leading-tight">Confirm your request</h1>
        <p class="mt-3 leading-relaxed text-ink-soft">Please confirm that you want to allow {{ $agency }} to request Manager access to the following business.</p>

        <dl class="mt-6 divide-y divide-line rounded-2xl border border-line">
            <div class="p-4">
                <dt class="eyebrow">Business</dt>
                <dd class="mt-1 text-lg font-semibold">{{ $client->google_location_title }}</dd>
            </div>
            <div class="p-4">
                <dt class="eyebrow">Address</dt>
                <dd class="mt-1">{{ $client->google_location_address ?: 'No street address shown (service area business)' }}</dd>
            </div>
            <div class="p-4">
                <dt class="eyebrow">Requesting access</dt>
                <dd class="mt-1">{{ $agency }}</dd>
            </div>
            <div class="p-4">
                <dt class="eyebrow">Access requested</dt>
                <dd class="mt-1"><strong>Manager</strong> <span class="text-sm text-ink-soft">(you stay the owner)</span></dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('onboarding.authorize', ['token' => $token]) }}" data-loading class="mt-6">
            @csrf
            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 has-[:checked]:border-pine has-[:checked]:bg-pine-tint">
                <input type="checkbox" name="authorize" value="1" class="mt-1 size-5 shrink-0 accent-[var(--color-pine)]" required>
                <span class="text-sm leading-relaxed">I authorize {{ $agency }} to request Manager access to this Google Business Profile for the purpose of providing SEO services.</span>
            </label>
            @error('authorize')<p class="field-error">{{ $message }}</p>@enderror

            <button type="submit" class="btn-primary btn-lg mt-6 w-full"><span data-label>Authorize Manager Access Request</span></button>
        </form>

        <p class="mt-5 text-center text-sm">
            @if ($only)
                <span class="text-ink-soft">Not your business?</span>
                <a href="mailto:{{ config('gbp.support_email') }}" class="font-semibold text-pine underline underline-offset-2">Tell us</a>
            @else
                <a href="{{ route('onboarding.profiles', ['token' => $token]) }}" class="font-semibold text-pine underline underline-offset-2">Choose a different business</a>
            @endif
        </p>
    </div>
@endsection
