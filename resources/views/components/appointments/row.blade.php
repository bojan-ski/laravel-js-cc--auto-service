@props(['appointment'])

<tr role="row" class="relative grid grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-2 border-b border-border p-3 last:border-b-0 hover:bg-surface-muted md:table-row md:p-0">
    <td role="cell" class="font-medium tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $appointment->scheduled_at->isoFormat('HH:mm') }}
    </td>

    <td role="cell" class="tabular-nums md:table-cell md:px-3 md:py-2">
        <a
            href="{{ route('appointments.show', $appointment) }}"
            aria-label="{{ __('appointments.show.title', [
                'plate' => $appointment->vehicle->registration_plate,
                'time' => $appointment->scheduled_at->isoFormat('D MMM HH:mm'),
            ]) }}"
            class="font-medium underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
        >{{ $appointment->vehicle->registration_plate }}</a>
    </td>

    <td role="cell" class="hidden lg:table-cell lg:px-3 lg:py-2">
        {{ $appointment->customer->name }}
    </td>

    <td role="cell" class="md:table-cell md:px-3 md:py-2">
        <x-ui.status-badge :status="$appointment->status" />
    </td>
</tr>
