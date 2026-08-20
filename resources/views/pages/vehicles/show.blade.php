<x-layouts.app :title="$vehicle->registration_plate">
    <x-vehicles.show-header :vehicle="$vehicle" />

    <x-vehicles.identity :vehicle="$vehicle" />

    <x-vehicles.service-history />
</x-layouts.app>
