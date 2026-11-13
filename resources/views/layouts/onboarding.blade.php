<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Connect your Google Business Profile') · {{ config('gbp.agency_name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,700|instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 pb-10 pt-5 sm:px-6 sm:pt-8">
        <header class="mb-8 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink font-display text-sm font-bold text-white">{{ config('gbp.agency_short_name') }}</span>
                <div class="leading-tight">
                    <p class="font-display text-lg font-bold">{{ config('gbp.agency_name') }}</p>
                    <p class="text-xs text-ink-soft">SEO and Google Business Profile services</p>
                </div>
            </div>
            @if (request()->isSecure())
                <p class="flex shrink-0 items-center gap-1.5 rounded-full bg-pine-tint px-3 py-1.5 text-xs font-semibold text-pine-deep" title="This page is sent over an encrypted connection ({{ request()->getHost() }})">
                    <svg viewBox="0 0 16 16" class="size-3.5" fill="currentColor" aria-hidden="true"><path d="M8 1a3.5 3.5 0 0 0-3.5 3.5V6H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-.5V4.5A3.5 3.5 0 0 0 8 1Zm2 5H6V4.5a2 2 0 1 1 4 0V6Z"/></svg>
                    Secure page
                </p>
            @endif
        </header>

        <main class="flex-1">
            @isset($step)
                <x-route-progress :step="$step" />
            @endisset
            @yield('content')
        </main>

        <footer class="mt-12 border-t border-line pt-6 text-sm text-ink-soft">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-1">
                    <p class="font-semibold text-ink">{{ config('gbp.agency_name') }}</p>
                    @if (config('gbp.company_address'))<p>{{ config('gbp.company_address') }}</p>@endif
                    @if (config('gbp.company_website'))
                        <p><a class="text-pine underline underline-offset-2" href="{{ config('gbp.company_website') }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://#', '', rtrim(config('gbp.company_website'), '/')) }}</a></p>
                    @endif
                </div>
                <div class="space-y-1">
                    <p class="font-semibold text-ink">Questions? Talk to a person</p>
                    <p><a class="text-pine underline underline-offset-2" href="mailto:{{ config('gbp.support_email') }}">{{ config('gbp.support_email') }}</a></p>
                    @if (config('gbp.support_phone'))<p><a class="text-pine underline underline-offset-2" href="tel:{{ preg_replace('/[^+\d]/', '', config('gbp.support_phone')) }}">{{ config('gbp.support_phone') }}</a></p>@endif
                </div>
            </div>
            <p class="mt-6 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                <a class="underline underline-offset-2" href="{{ route('legal.privacy') }}">Privacy policy</a>
                <a class="underline underline-offset-2" href="{{ route('legal.terms') }}">Terms of service</a>
                <span>We never see or store your Google password.</span>
            </p>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
