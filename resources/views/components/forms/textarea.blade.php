@props([
    'name',
    'label',
    'value' => null,
    'rows' => 4,
])

@php
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'mt-1 block w-full rounded-sm border bg-surface px-3 py-2 text-text focus:outline-2 focus:outline-offset-2 focus:outline-accent',
            'border-border focus:border-accent' => ! $hasError,
            'border-status-cancelled' => $hasError,
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</div>
