@props([
    'action',
    'method' => 'PATCH',
    'confirm' => null,
    'variant' => 'secondary',
    'ariaLabel' => null,
])

<form method="POST" action="{{ $action }}"@if ($confirm !== null) data-confirm="{{ $confirm }}"@endif>
    @csrf
    @method($method)

    <x-ui.button type="submit" :variant="$variant" :aria-label="$ariaLabel" {{ $attributes }}>
        {{ $slot }}
    </x-ui.button>
</form>
