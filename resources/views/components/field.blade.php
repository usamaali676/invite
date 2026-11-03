@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null])
<div>
    <label for="{{ $name }}" class="field-label">{{ $label }} @unless ($required)<span class="font-normal text-ink-faint">(optional)</span>@endunless</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @required($required) placeholder="{{ $placeholder }}" class="field">{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'field']) }}>
    @endif
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
