@props(['vehicle'])

<tr role="row" class="grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 font-medium tabular-nums md:table-cell md:px-3 md:py-2">
        {{ $vehicle->registration_plate }}
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm text-text-muted md:table-cell md:px-3 md:py-2">
        {{ $vehicle->make }} {{ $vehicle->model }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2">
        @can('restore', $vehicle)
            <x-ui.action-form
                :action="route('vehicles.restore', $vehicle)"
                :confirm="__('vehicles.deleted.confirm', ['plate' => $vehicle->registration_plate])"
                :aria-label="__('vehicles.deleted.restore_vehicle', ['plate' => $vehicle->registration_plate])"
            >
                {{ __('vehicles.deleted.restore') }}
            </x-ui.action-form>
        @endcan
    </td>
</tr>
