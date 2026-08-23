<x-layouts.app :title="__('jobs.form.create_title')">
    <h1 class="text-2xl font-bold">{{ __('jobs.form.create_title') }}</h1>

    <x-jobs.intake-form :appointment="$appointment ?? null" :vehicle-options="$vehicleOptions ?? []" />
</x-layouts.app>
