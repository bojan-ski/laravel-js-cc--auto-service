@props(['appointment'])

<tr role="row" class="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="font-medium tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $appointment->scheduled_at->isoFormat('HH:mm') }}
    </td>

    <td role="cell" class="tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $appointment->vehicle->registration_plate }}
    </td>

    <td role="cell" class="hidden lg:table-cell lg:px-3 lg:py-2">
        {{ $appointment->customer->name }}
    </td>

    <td role="cell" class="col-span-2 md:table-cell md:px-3 md:py-2">
        <x-ui.status-badge :status="$appointment->status" />
    </td>

    <td role="cell" class="col-span-2 md:table-cell md:px-3 md:py-2">
        <x-appointments.row-actions :appointment="$appointment" />
    </td>
</tr>
