@props([
    'name',
    'label',
    'checked' => false,
])

<div class="flex items-center gap-2">
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="checkbox"
        value="1"
        @checked(old($name, $checked))
        {{ $attributes->class([
            'size-4 rounded-sm border-border-strong text-brand focus:outline-2 focus:outline-offset-2 focus:outline-accent',
        ]) }}
    >

    <label for="{{ $name }}" class="text-sm">{{ $label }}</label>
</div>
