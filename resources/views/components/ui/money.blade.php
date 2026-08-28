@props(['value'])

<span {{ $attributes->class('tabular-nums') }}>{{ number_format((float) $value, 2) }}</span>
