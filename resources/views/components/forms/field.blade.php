@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'readonly' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @readonly($readonly)
        {{ $attributes->class([
            'mt-1 block w-full rounded-sm border px-3 py-2 focus:outline-2 focus:outline-offset-2 focus:outline-accent',
            'bg-surface text-text' => ! $readonly,
            'bg-surface-muted text-text-soft' => $readonly,
            'border-border focus:border-accent' => ! $hasError,
            'border-status-cancelled' => $hasError,
        ]) }}
    >

    @error($name)
        <p class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</div>
