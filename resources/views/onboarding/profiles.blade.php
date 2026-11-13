@extends('layouts.onboarding')

@section('title', 'Choose your business')

@section('content')
    <div class="card p-6 sm:p-8">
        <h1 class="text-3xl font-bold leading-tight">Which business is yours?</h1>
        <p class="mt-3 leading-relaxed text-ink-soft">We found these business listings in your Google account. Choose the one for <strong class="text-ink">{{ $client->business_name }}</strong>.</p>
        @include('onboarding.partials.signed-in')

        <form method="POST" action="{{ route('onboarding.select', ['token' => $token]) }}" data-loading class="mt-6">
            @csrf
            <fieldset>
                <legend class="sr-only">Your business listings</legend>
                <div class="space-y-3">
                    @foreach ($locations as $location)
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 transition has-[:checked]:border-pine has-[:checked]:bg-pine-tint has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-pine">
                            <input type="radio" name="location" value="{{ $location['name'] }}" class="mt-1 size-5 accent-[var(--color-pine)]" required @checked(old('location', $selected) === $location['name'])>
                            <span class="min-w-0">
                                <span class="block text-base font-semibold">{{ $location['title'] }}</span>
                                <span class="block text-sm text-ink-soft">{{ $location['address'] ?: 'No street address shown (service area business)' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('location')<p class="field-error">{{ $message }}</p>@enderror

            <button type="submit" class="btn-primary btn-lg mt-6 w-full"><span data-label>Continue</span></button>
        </form>
    </div>
@endsection
