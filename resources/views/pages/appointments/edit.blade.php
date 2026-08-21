<x-layouts.app :title="__('appointments.form.edit_title')">
    <h1 class="text-2xl font-bold">{{ __('appointments.form.edit_title') }}</h1>

    <x-appointments.form :appointment="$appointment" />
</x-layouts.app>
