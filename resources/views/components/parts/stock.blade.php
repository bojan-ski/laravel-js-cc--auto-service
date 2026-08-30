@props(['part'])

@php
    $negative = $part->hasNegativeStock();
    $low = $part->isLowStock();
@endphp

<span {{ $attributes->class(['inline-flex items-baseline gap-1.5']) }}>
    <span @class([
        'tabular-nums',
        'font-bold text-status-low-stock' => $negative,
        'font-medium text-status-low-stock' => $low && ! $negative,
    ])>{{ $part->quantity_in_stock }}</span>

    @if ($negative)
        <span class="text-xs font-semibold uppercase tracking-wider text-status-low-stock">{{ __('parts.stock.negative') }}</span>
    @elseif ($low)
        <span class="text-xs font-medium uppercase tracking-wider text-status-low-stock">{{ __('parts.stock.low') }}</span>
    @endif
</span>
