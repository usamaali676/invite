@props(['status'])
@php
    $tones = [
        'green' => 'bg-green-100 text-green-800',
        'red' => 'bg-red-100 text-red-800',
        'amber' => 'bg-amber-100 text-amber-900',
        'blue' => 'bg-sky-100 text-sky-800',
        'slate' => 'bg-slate-200 text-slate-700',
        'plain' => 'bg-mist text-ink-soft ring-1 ring-inset ring-line',
    ];
@endphp
<span {{ $attributes->class(['badge', $tones[$status->tone()]]) }}>{{ $status->label() }}</span>
