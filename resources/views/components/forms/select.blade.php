@props([
    'name',
    'label',
    'options',
    'selected' => null,
    'placeholder' => null,
])

@php
    $hasError = $errors->has($name);
    $current = old($name, $selected);
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'mt-1 block w-full rounded-sm border bg-surface px-3 py-2 text-text focus:outline-2 focus:outline-offset-2 focus:outline-accent',
            'border-border focus:border-accent' => ! $hasError,
            'border-status-cancelled' => $hasError,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $option)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $option }}</option>
        @endforeach
    </select>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</div>
