<x-layouts.app :title="__('vehicles.reassign.title', ['plate' => $vehicle->registration_plate])">
    <h1 class="text-2xl font-bold">{{ __('vehicles.reassign.title', ['plate' => $vehicle->registration_plate]) }}</h1>

    <x-vehicles.reassign-form :vehicle="$vehicle" :customers="$customers" />
</x-layouts.app>
