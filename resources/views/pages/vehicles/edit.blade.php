<x-layouts.app :title="__('vehicles.edit.title', ['plate' => $vehicle->registration_plate])">
    <h1 class="text-2xl font-bold">{{ __('vehicles.edit.title', ['plate' => $vehicle->registration_plate]) }}</h1>

    <x-vehicles.form
        :action="route('vehicles.update', $vehicle)"
        method="PUT"
        :owner="$vehicle->customer"
        :vehicle="$vehicle"
        :submit="__('vehicles.edit.submit')"
        :cancel="route('vehicles.show', $vehicle)"
    />
</x-layouts.app>
