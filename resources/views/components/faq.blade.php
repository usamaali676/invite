@props(['question'])
<details {{ $attributes->class(['group border-b border-line last:border-b-0']) }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-left text-base font-semibold">
        {{ $question }}
        <svg viewBox="0 0 20 20" class="size-5 shrink-0 text-ink-faint transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 5 5 5-5"/></svg>
    </summary>
    <div class="pb-4 text-sm leading-relaxed text-ink-soft">{{ $slot }}</div>
</details>
