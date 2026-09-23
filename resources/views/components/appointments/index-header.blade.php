@props(['hours' => null])

<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('appointments.title') }}</h1>

    <div class="flex flex-wrap items-center gap-2">
        @if ($hours)
            <x-ui.button variant="secondary" :href="route('admin.appointment-hours.edit')" data-dialog-open="appointment-hours">
                {{ __('appointments.index.set_hours') }}
            </x-ui.button>
        @endif

        @can('create', App\Models\Appointment::class)
            <x-ui.button :href="route('appointments.create')">
                {{ __('appointments.index.new_appointment') }}
            </x-ui.button>
        @endcan
    </div>
</div>

@if ($hours)
    <x-appointments.hours-dialog :hours="$hours" />
@endif
