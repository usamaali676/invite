@props(['step' => 1])
@php
    $stops = ['Connect Google', 'Choose business', 'Confirm', 'Done'];
@endphp
<nav aria-label="Progress" class="mb-8">
    <ol class="flex items-start">
        @foreach ($stops as $index => $label)
            @php
                $number = $index + 1;
                $state = $number < $step ? 'done' : ($number === $step ? 'current' : 'todo');
            @endphp
            <li class="flex flex-1 flex-col items-center text-center {{ $loop->last ? 'flex-none' : '' }}" @if ($state === 'current') aria-current="step" @endif>
                <div class="flex w-full items-center">
                    {{-- marker --}}
                    <span class="relative z-10 grid size-9 shrink-0 place-items-center {{ $loop->first ? '' : '' }}">
                        @if ($state === 'current')
                            <svg viewBox="0 0 36 44" class="h-9 w-7 drop-shadow-sm" aria-hidden="true">
                                <path d="M18 2C9.7 2 3 8.5 3 16.6 3 27.6 18 42 18 42s15-14.400 15-25.400C33 8.500 26.300 2 18 2Z" fill="var(--color-pin)"/>
                                <circle cx="18" cy="16.500" r="5.500" fill="#fff"/>
                            </svg>
                        @elseif ($state === 'done')
                            <span class="grid size-7 place-items-center rounded-full bg-pine text-white">
                                <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.500" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.500 8.500 3 3 6-7"/></svg>
                            </span>
                        @else
                            <span class="size-5 rounded-full border-2 border-line bg-paper"></span>
                        @endif
                    </span>
                    {{-- route to the next stop --}}
                    @unless ($loop->last)
                        <span class="-mx-1 h-0 flex-1 border-t-2 {{ $state === 'done' ? 'border-solid border-pine' : 'border-dashed border-line' }}"></span>
                    @endunless
                </div>
                <span class="mt-1.5 w-full text-[0.7rem] font-semibold leading-tight sm:text-xs {{ $state === 'todo' ? 'text-ink-faint' : 'text-ink' }} {{ $loop->last ? 'text-right' : 'text-left' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
