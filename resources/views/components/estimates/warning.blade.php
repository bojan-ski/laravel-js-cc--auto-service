@props(['summary'])

@php
    $number = $summary['estimate']->number;

    $text = match ($summary['warning']) {
        'over' => __('estimates.warning.over', ['amount' => number_format((float) $summary['over_by'], 2), 'number' => $number]),
        'declined' => __('estimates.warning.declined', ['number' => $number]),
        default => __('estimates.warning.not_approved', ['number' => $number]),
    };
@endphp

<p {{ $attributes->class('text-sm text-text') }}>
    <span aria-hidden="true">&#9888;</span>
    {{ $text }}
</p>
