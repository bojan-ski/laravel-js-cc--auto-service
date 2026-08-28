@props(['appointment'])

@php
    $context = [
        'plate' => $appointment->vehicle->registration_plate,
        'time' => $appointment->scheduled_at->isoFormat('D MMM HH:mm'),
    ];
@endphp

<div class="flex flex-wrap justify-end gap-1">
    @can('markArrived', $appointment)
        <x-ui.button
            variant="secondary"
            :href="route('jobs.create', ['appointment' => $appointment->getKey()])"
            :aria-label="__('appointments.actions.mark_arrived_for', $context)"
        >
            {{ __('appointments.actions.arrived') }}
        </x-ui.button>
    @endcan

    @can('markNoShow', $appointment)
        <x-ui.action-form
            :action="route('appointments.no-show', $appointment)"
            :aria-label="__('appointments.actions.mark_no_show_for', $context)"
        >
            {{ __('appointments.actions.no_show') }}
        </x-ui.action-form>
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
        <x-ui.action-form
            variant="danger"
            :action="route('appointments.cancel', $appointment)"
            :confirm="__('appointments.actions.confirm_cancel', $context)"
            :aria-label="__('appointments.actions.cancel_for', $context)"
        >
            {{ __('appointments.actions.cancel') }}
        </x-ui.action-form>
    @endcan
</div>
