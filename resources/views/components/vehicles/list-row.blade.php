@props(['vehicle'])

<tr role="row" class="grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 font-medium tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $vehicle->registration_plate }}
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm text-text-muted md:table-cell md:px-3 md:py-2 md:text-base md:text-text">
        {{ $vehicle->make }} {{ $vehicle->model }}
    </td>

    <td role="cell" class="hidden tabular-nums md:table-cell md:px-3 md:py-2">
        <x-ui.value :value="$vehicle->year" />
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2">
        <x-ui.button
            variant="secondary"
            :href="route('vehicles.show', $vehicle)"
            :aria-label="__('vehicles.section.view_vehicle', ['plate' => $vehicle->registration_plate])"
        >
            {{ __('app.actions.view') }}
        </x-ui.button>
    </td>
</tr>
