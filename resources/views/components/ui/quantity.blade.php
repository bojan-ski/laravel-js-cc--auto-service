@props(['value'])

@php
    $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

<span {{ $attributes->class('tabular-nums') }}>{{ $formatted === '' ? '0' : $formatted }}</span>
