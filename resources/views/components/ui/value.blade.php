@props(['value'])

@if ($value === null || $value === '')
    <span aria-hidden="true">{{ __('app.value_none') }}</span><span class="sr-only">{{ __('app.value_none_spoken') }}</span>
@else
    {{ $value }}
@endif
