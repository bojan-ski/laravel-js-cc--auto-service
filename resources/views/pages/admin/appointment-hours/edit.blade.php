<x-layouts.app :title="__('appointments.hours.title')">
    <h1 class="text-2xl font-bold">{{ __('appointments.hours.title') }}</h1>

    <x-appointments.hours-form :hours="$hours" class="mt-4 max-w-md border border-border bg-surface p-4" />
</x-layouts.app>
