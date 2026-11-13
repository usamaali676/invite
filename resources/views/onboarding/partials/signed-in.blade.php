@if (filled($googleName ?? null))
    <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl bg-mist px-4 py-3 text-sm">
        <p class="text-ink-soft">Signed in with Google as <strong class="text-ink">{{ $googleName }}</strong></p>
        <form method="POST" action="{{ route('onboarding.start', ['token' => $token]) }}" data-loading>
            @csrf
            <button type="submit" class="btn-link">Not you? Use another account</button>
        </form>
    </div>
@endif
