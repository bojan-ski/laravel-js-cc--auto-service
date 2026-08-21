@props([
    'appointment' => null,
    'vehicleOptions' => [],
])

@php
    $isEdit = $appointment !== null;
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('appointments.update', $appointment) : route('appointments.store') }}"
    class="mt-4 max-w-2xl space-y-4 border border-border bg-surface p-4"
    data-appointment-overlap
    data-appointment-overlap-url="{{ route('appointments.overlaps') }}"
    data-appointment-overlap-template="{{ __('appointments.overlap.warning', ['count' => ':count']) }}"
    @if ($isEdit) data-appointment-overlap-exclude="{{ $appointment->getKey() }}" @endif
>
    @csrf

    @if ($isEdit)
        @method('PUT')

        <div>
            <p class="block text-sm font-medium">{{ __('appointments.form.vehicle_fixed') }}</p>

            <p class="mt-1 tabular-nums">
                {{ __('appointments.form.vehicle_option', [
                    'plate' => $appointment->vehicle->registration_plate,
                    'vehicle' => $appointment->vehicle->make.' '.$appointment->vehicle->model,
                    'owner' => $appointment->customer->name,
                ]) }}
            </p>

            <p class="mt-1 text-sm text-text-muted">{{ __('appointments.form.vehicle_fixed_hint') }}</p>
        </div>
    @else
        <x-forms.select
            name="vehicle_id"
            :label="__('appointments.form.vehicle')"
            :options="$vehicleOptions"
            :placeholder="__('appointments.form.vehicle_placeholder')"
            required
        />
    @endif

    <x-forms.field
        name="scheduled_at"
        type="datetime-local"
        :label="__('appointments.form.scheduled_at')"
        :value="$isEdit ? $appointment->scheduled_at->format('Y-m-d\TH:i') : null"
        data-appointment-overlap-input
        required
    />

    <p data-appointment-overlap-output class="text-sm text-status-awaiting-parts" role="status" aria-live="polite"></p>

    <x-forms.field
        name="duration_minutes"
        type="number"
        :label="__('appointments.form.duration_minutes')"
        :value="$isEdit ? $appointment->duration_minutes : 60"
        min="15"
        max="480"
        data-appointment-overlap-input
        required
    />

    <x-forms.textarea name="notes" :label="__('appointments.form.notes')" :value="$appointment?->notes" />

    <div class="flex items-center gap-3">
        <x-ui.button type="submit">
            {{ $isEdit ? __('appointments.form.submit_update') : __('appointments.form.submit_create') }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('appointments.index')">
            {{ __('appointments.form.back') }}
        </x-ui.button>
    </div>
</form>
