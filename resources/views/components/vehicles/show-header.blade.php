@props(['vehicle'])

<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">
        <span class="tabular-nums">{{ $vehicle->registration_plate }}</span>
        <span class="text-text-muted">·</span>
        {{ $vehicle->make }} {{ $vehicle->model }}
    </h1>

    <div class="flex flex-wrap gap-2">
        @can('update', $vehicle)
            <x-ui.button
                variant="secondary"
                :href="route('vehicles.edit', $vehicle)"
                :aria-label="__('vehicles.edit.title', ['plate' => $vehicle->registration_plate])"
            >
                {{ __('app.actions.edit') }}
            </x-ui.button>
        @endcan

        @can('reassign', $vehicle)
            <x-ui.button
                variant="secondary"
                :href="route('vehicles.owner.edit', $vehicle)"
                :aria-label="__('vehicles.reassign.action_vehicle', ['plate' => $vehicle->registration_plate])"
            >
                {{ __('vehicles.reassign.action') }}
            </x-ui.button>
        @endcan

        @can('delete', $vehicle)
            <form
                method="POST"
                action="{{ route('vehicles.destroy', $vehicle) }}"
                data-confirm="{{ __('vehicles.delete.confirm', ['plate' => $vehicle->registration_plate]) }}"
            >
                @csrf
                @method('DELETE')

                <x-ui.button
                    type="submit"
                    variant="danger"
                    :aria-label="__('vehicles.delete.action', ['plate' => $vehicle->registration_plate])"
                >
                    {{ __('app.actions.delete') }}
                </x-ui.button>
            </form>
        @endcan
    </div>
</div>
