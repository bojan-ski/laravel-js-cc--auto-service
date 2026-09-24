<x-layouts.app :title="__('appointments.form.create_title')">
    <h1 class="text-2xl font-bold">{{ __('appointments.form.create_title') }}</h1>

    <x-appointments.form :vehicle-options="$vehicleOptions" :hours="$hours" :earliest="$earliest" />
</x-layouts.app>
