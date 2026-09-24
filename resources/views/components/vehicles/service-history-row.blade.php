@props(['job'])

@php
    $mileage = $job->mileage_in === null
        ? null
        : __('jobs.show.mileage_value', ['mileage' => number_format($job->mileage_in)]);
@endphp

<tr role="row" class="relative grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 hover:bg-surface-muted md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-1.5">
        <a
            href="{{ route('jobs.show', $job) }}"
            class="font-medium tabular-nums underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
        >
            {{ $job->reference }}
        </a>
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm text-text-muted md:table-cell md:px-3 md:py-1.5 md:text-base md:text-text">
        {{ $job->customer->name }}

        @if ($job->customer->trashed())
            <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
        @endif
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-3 self-center text-end md:table-cell md:px-3 md:py-1.5 md:text-start">
        <x-ui.status-badge :status="$job->status" />
    </td>

    <td role="cell" class="col-start-1 row-start-3 text-sm tabular-nums text-text-muted md:table-cell md:px-3 md:py-1.5 md:text-base md:text-text">
        {{ $job->created_at->isoFormat('D MMM YYYY') }}
    </td>

    <td role="cell" @class([
        'hidden tabular-nums md:table-cell md:px-3 md:py-1.5',
        'text-text-muted' => $mileage === null,
    ])>
        <x-ui.value :value="$mileage" />
    </td>
</tr>
