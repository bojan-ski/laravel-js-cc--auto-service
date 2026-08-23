@props(['job'])

<tr role="row" class="grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        <span class="block font-medium tabular-nums">{{ $job->vehicle->registration_plate }}</span>
        <span class="block text-sm tabular-nums text-text-muted">{{ $job->reference }}</span>
    </td>

    <td role="cell" class="hidden xl:table-cell xl:px-3 xl:py-2">
        {{ $job->vehicle->make }} {{ $job->vehicle->model }}
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm text-text-muted md:table-cell md:px-3 md:py-2 md:text-base md:text-text">
        <span class="md:hidden">{{ $job->vehicle->make }} {{ $job->vehicle->model }} &middot; </span>{{ $job->customer->name }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2 md:text-start">
        <x-ui.status-badge :status="$job->status" />
    </td>

    <td role="cell" class="hidden tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $job->created_at->isoFormat('D MMM HH:mm') }}
    </td>

    <td role="cell" class="hidden text-text-muted xl:table-cell xl:px-3 xl:py-2">
        <x-ui.value :value="null" />
    </td>
</tr>
