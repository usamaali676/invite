<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ config('gbp.agency_short_name') }} Profile Access</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,700|instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center px-4 py-10">
    <main class="w-full max-w-sm">
        <div class="mb-8 flex items-center gap-3">
            <span class="grid size-11 place-items-center rounded-xl bg-ink font-display font-bold text-white">{{ config('gbp.agency_short_name') }}</span>
            <div>
                <h1 class="font-display text-xl font-bold leading-tight">Profile Access</h1>
                <p class="text-sm text-ink-soft">{{ config('gbp.agency_name') }} staff sign in</p>
            </div>
        </div>

        <form method="POST" action="{{ route('login') }}" data-loading class="card space-y-5 p-6">
            @csrf
            <x-field name="email" label="Email" type="email" required autocomplete="username" autofocus />
            <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-pine"> Keep me signed in on this device
            </label>
            <button class="btn-primary w-full" type="submit"><span data-label>Sign in</span></button>
        </form>
    </main>
</body>
</html>
