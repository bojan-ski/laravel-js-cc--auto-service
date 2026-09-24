@props(['filters', 'hasAny'])

@if (! $hasAny)
    <x-ui.empty-state
        :title="__('appointments.empty.none_title')"
        :body="__('appointments.empty.none_body')"
    >
        @can('create', App\Models\Appointment::class)
            <x-ui.button :href="route('appointments.create')">
                {{ __('appointments.index.new_appointment') }}
            </x-ui.button>
        @endcan
    </x-ui.empty-state>
@elseif ($filters['status'] !== '')
    <x-ui.empty-state
        :title="__('appointments.empty.filtered_title')"
        :body="__('appointments.empty.filtered_body')"
    >
        <x-ui.button
            variant="secondary"
            :href="route('appointments.index', ['view' => $filters['view'], 'date' => $filters['date']->format('Y-m-d')])"
        >
            {{ __('appointments.empty.clear_filter') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        :title="__('appointments.empty.window_title')"
        :body="__('appointments.empty.window_body')"
    >
        <x-ui.button variant="secondary" :href="route('appointments.index', ['view' => $filters['view']])">
            {{ __('appointments.index.today') }}
        </x-ui.button>
    </x-ui.empty-state>
@endif
