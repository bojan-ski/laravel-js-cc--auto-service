@props(['vehicle'])

<tr role="row" class="relative grid grid-cols-1 items-center gap-x-3 border-b border-border p-3 last:border-b-0 hover:bg-surface-muted md:table-row md:p-0">
    <td role="cell" class="font-medium tabular-nums md:table-cell md:px-3 md:py-2">
        <a
            href="{{ route('vehicles.show', $vehicle) }}"
            class="underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
        >{{ $vehicle->registration_plate }}</a>
    </td>

    <td role="cell" class="text-sm text-text-muted md:table-cell md:px-3 md:py-2 md:text-base md:text-text">
        {{ $vehicle->make }} {{ $vehicle->model }}
    </td>

    <td role="cell" class="hidden tabular-nums md:table-cell md:px-3 md:py-2">
        <x-ui.value :value="$vehicle->year" />
    </td>
</tr>
