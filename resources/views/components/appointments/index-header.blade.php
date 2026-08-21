<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('appointments.title') }}</h1>

    @can('create', App\Models\Appointment::class)
        <x-ui.button :href="route('appointments.create')">
            {{ __('appointments.index.new_booking') }}
        </x-ui.button>
    @endcan
</div>
