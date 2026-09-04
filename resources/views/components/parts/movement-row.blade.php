@props(['movement'])

@php
    $job = $movement->jobItem?->job;
@endphp

<tr role="row" class="grid grid-cols-[1fr_auto] items-start gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 text-sm tabular-nums text-text-muted md:table-cell md:px-3 md:py-1.5 md:text-base md:text-text">
        {{ $movement->created_at->isoFormat('D MMM HH:mm') }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 text-end md:table-cell md:px-3 md:py-1.5 md:text-start">
        <span @class([
            'font-medium tabular-nums',
            'text-status-completed' => $movement->isIncrease(),
            'text-status-low-stock' => ! $movement->isIncrease(),
        ])>{{ sprintf('%+d', $movement->quantity_change) }}</span>
    </td>

    <td role="cell" class="col-span-2 col-start-1 row-start-2 md:table-cell md:px-3 md:py-1.5">
        {{ $movement->reason->label() }}

        @if ($job !== null)
            <a
                href="{{ route('jobs.show', $job) }}"
                class="ms-1 font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
            >
                {{ $job->reference }}
            </a>
        @endif

        @if ($movement->supplier !== null)
            <span class="ms-1 text-sm text-text-muted">{{ __('parts.movements.from_supplier', ['name' => $movement->supplier->name]) }}</span>
        @endif

        @if ($movement->note !== null)
            <p class="text-sm text-text-muted">{{ $movement->note }}</p>
        @endif
    </td>

    <td role="cell" class="hidden md:table-cell md:px-3 md:py-1.5">
        {{ $movement->causedBy->name }}

        @if ($movement->causedBy->trashed())
            <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
        @endif
    </td>
</tr>
