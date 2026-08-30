@props([
    'name',
    'label',
    'options',
    'selected' => null,
    'required' => false,
])

@php
    $hasError = $errors->has($name);
    $current = old($name, $selected);
@endphp

<fieldset
    role="radiogroup"
    aria-labelledby="{{ $name }}-label"
    @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    {{ $attributes->class([
        'border px-3 py-2',
        'border-border' => ! $hasError,
        'border-status-cancelled' => $hasError,
    ]) }}
>
    <legend id="{{ $name }}-label" class="px-1 text-sm font-medium">{{ $label }}</legend>

    <div class="space-y-3 py-1">
        @foreach ($options as $value => $option)
            <div class="flex items-start gap-2">
                <input
                    id="{{ $name }}-{{ $value }}"
                    name="{{ $name }}"
                    type="radio"
                    value="{{ $value }}"
                    @checked((string) $current === (string) $value)
                    @required($required)
                    @isset($option['help']) aria-describedby="{{ $name }}-{{ $value }}-help" @endisset
                    class="mt-1 size-4 shrink-0 accent-brand focus:outline-2 focus:outline-offset-2 focus:outline-accent"
                >

                <div>
                    <label for="{{ $name }}-{{ $value }}" class="block text-sm font-medium">{{ $option['label'] }}</label>

                    @isset($option['help'])
                        <p id="{{ $name }}-{{ $value }}-help" class="text-sm text-text-muted">{{ $option['help'] }}</p>
                    @endisset
                </div>
            </div>
        @endforeach
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-status-cancelled">{{ $message }}</p>
    @enderror
</fieldset>
