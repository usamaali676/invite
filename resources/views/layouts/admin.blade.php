<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') · {{ config('gbp.agency_short_name') }} Profile Access</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,700|instrument-sans:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="border-b border-line bg-paper">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-xl bg-ink font-display text-sm font-bold text-white">{{ config('gbp.agency_short_name') }}</span>
                <span class="hidden font-display text-base font-bold sm:block">Profile Access</span>
            </a>
            <nav class="flex items-center gap-1 text-sm font-semibold">
                <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('admin.dashboard', 'admin.clients.*') ? 'bg-pine-tint text-pine-deep' : 'text-ink-soft hover:text-ink' }}">Clients</a>
                <a href="{{ route('admin.settings.edit') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('admin.settings.*') ? 'bg-pine-tint text-pine-deep' : 'text-ink-soft hover:text-ink' }}">Settings</a>
                <form method="POST" action="{{ route('logout') }}" data-no-loading>
                    @csrf
                    <button class="rounded-lg px-3 py-2 text-ink-soft hover:text-ink">Log out</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @include('partials.flash')
        @yield('content')
    </main>

    @include('partials.confirm-dialog')
</body>
</html>
