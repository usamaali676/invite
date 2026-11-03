@extends('layouts.onboarding')

@section('title', 'Approve '.$agency)

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="eyebrow">For {{ $client->business_name }}</p>

        @if ($connected)
            <h1 class="mt-2 text-3xl font-bold leading-tight">You're connected</h1>
            <p class="mt-4 leading-relaxed text-ink-soft">Your Google Business Profile is successfully connected with {{ $agency }}. There is nothing else you need to do.</p>
        @elseif ($submitted)
            <h1 class="mt-2 text-3xl font-bold leading-tight">Please approve our request</h1>
            <p class="mt-4 leading-relaxed text-ink-soft">{{ $agency }} has asked Google for Manager access to your business. Google needs you, as the owner, to say yes. It takes about two minutes.</p>

            <ol class="mt-6 space-y-5">
                @foreach ([
                    ['Open your Business Profile', 'Go to business.google.com and sign in with the Google account that owns '.$client->business_name.'. You can also search for your business name on Google while signed in.'],
                    ['Find the request', 'Look for a notification or open Manage invites in your Business Profile settings. You will see a request from '.$agency.'.'],
                    ['Approve it', 'Choose Approve. Check that the access listed is Manager, not Owner.'],
                ] as [$title, $detail])
                    <li class="flex gap-4">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-pine font-display text-sm font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                        <span><span class="block font-semibold">{{ $title }}</span><span class="block text-sm leading-relaxed text-ink-soft">{{ $detail }}</span></span>
                    </li>
                @endforeach
            </ol>

            <a href="https://www.google.com/business/" target="_blank" rel="noopener noreferrer" class="btn-primary btn-lg mt-8 w-full">Open my Business Profile</a>
            <p class="mt-4 text-center text-sm text-ink-soft">That's all. We'll take it from there once you approve.</p>
        @else
            <h1 class="mt-2 text-3xl font-bold leading-tight">We're preparing your request</h1>
            <p class="mt-4 leading-relaxed text-ink-soft">{{ $agency }} is sending a request for Manager access to your Google Business Profile. You'll get a message from us with simple steps to approve it, so there is nothing to do right now.</p>
        @endif
    </div>
@endsection
