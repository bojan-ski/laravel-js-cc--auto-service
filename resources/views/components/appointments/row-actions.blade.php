@props(['appointment'])

@php
    $context = [
        'plate' => $appointment->vehicle->registration_plate,
        'time' => $appointment->scheduled_at->isoFormat('D MMM HH:mm'),
    ];
@endphp

<div class="flex flex-wrap justify-end gap-1">
    @can('markArrived', $appointment)
        <form method="POST" action="{{ route('appointments.arrive', $appointment) }}">
            @csrf
            @method('PATCH')

            <x-ui.button variant="secondary" :aria-label="__('appointments.actions.mark_arrived_for', $context)">
                {{ __('appointments.actions.arrived') }}
            </x-ui.button>
        </form>
    @endcan

    @can('markNoShow', $appointment)
        <form method="POST" action="{{ route('appointments.no-show', $appointment) }}">
            @csrf
            @method('PATCH')

            <x-ui.button variant="secondary" :aria-label="__('appointments.actions.mark_no_show_for', $context)">
                {{ __('appointments.actions.no_show') }}
            </x-ui.button>
        </form>
    @endcan

    @can('update', $appointment)
        <x-ui.button
            variant="secondary"
            :href="route('appointments.edit', $appointment)"
            :aria-label="__('appointments.actions.edit_for', $context)"
        >
            {{ __('appointments.actions.edit') }}
        </x-ui.button>
    @endcan

    @can('cancel', $appointment)
        <form
            method="POST"
            action="{{ route('appointments.cancel', $appointment) }}"
            data-confirm="{{ __('appointments.actions.confirm_cancel', $context) }}"
        >
            @csrf
            @method('PATCH')

            <x-ui.button variant="danger" :aria-label="__('appointments.actions.cancel_for', $context)">
                {{ __('appointments.actions.cancel') }}
            </x-ui.button>
        </form>
    @endcan
</div>
