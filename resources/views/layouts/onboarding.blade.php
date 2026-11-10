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
    <div class="mx-auto flex min-h-screen w-full max-w-xl flex-col px-4 pb-10 pt-6 sm:px-6 sm:pt-10">
        <header class="mb-8 flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-ink font-display text-sm font-bold text-white">{{ config('gbp.agency_short_name') }}</span>
            <span class="font-display text-lg font-bold leading-tight">{{ config('gbp.agency_name') }}</span>
        </header>

        <main class="flex-1">
            @isset($step)
                <x-route-progress :step="$step" />
            @endisset
            @yield('content')
        </main>

        <footer class="mt-10 border-t border-line pt-5 text-xs leading-relaxed text-ink-soft">
            <p>Need help? Email <a class="font-semibold text-pine underline underline-offset-2" href="mailto:{{ config('gbp.support_email') }}">{{ config('gbp.support_email') }}</a>@if (config('gbp.support_phone')) or call {{ config('gbp.support_phone') }}@endif.</p>
            <p class="mt-1">We never see your Google password. Google handles sign-in and asks for your permission.</p>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
