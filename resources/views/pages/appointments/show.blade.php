@php
    $title = __('appointments.show.title', [
        'plate' => $appointment->vehicle->registration_plate,
        'time' => $appointment->scheduled_at->isoFormat('D MMM HH:mm'),
    ]);
@endphp

<x-layouts.app :title="$title">
    <x-appointments.show-header :appointment="$appointment" />

    <x-appointments.details :appointment="$appointment" />
</x-layouts.app>
