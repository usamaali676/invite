@foreach ([
    'status' => 'border-green-200 bg-green-50 text-green-900',
    'notice' => 'border-amber-200 bg-amber-50 text-amber-900',
    'error' => 'border-red-200 bg-red-50 text-red-900',
] as $key => $classes)
    @if (session($key))
        <div role="{{ $key === 'error' ? 'alert' : 'status' }}" class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium {{ $classes }}">
            {{ session($key) }}
        </div>
    @endif
@endforeach
