@props([
    'appointment' => null,
    'vehicleOptions' => [],
])

@php
    $fromAppointment = $appointment !== null;
    $lastKnownMileage = $appointment?->vehicle->mileage;
    $mileageHintId = $lastKnownMileage !== null && ! $errors->has('mileage_in') ? 'mileage_in-hint' : null;
@endphp

<form method="POST" action="{{ route('jobs.store') }}" class="mt-4 max-w-2xl space-y-4 border border-border bg-surface p-4">
    @csrf

    @if ($fromAppointment)
        <input type="hidden" name="appointment_id" value="{{ $appointment->getKey() }}">

        <x-jobs.appointment-context :appointment="$appointment" />
    @else
        <x-customers.intake-picker :vehicle-options="$vehicleOptions" />
    @endif

    <x-forms.textarea
        name="complaint"
        :label="__('jobs.form.complaint')"
        :placeholder="__('jobs.form.complaint_placeholder')"
        maxlength="2000"
        required
    />

    <div>
        <x-forms.field
            name="mileage_in"
            type="number"
            :label="__('jobs.form.mileage_in')"
            min="0"
            step="1"
            inputmode="numeric"
            :aria-describedby="$mileageHintId"
        />

        @if ($lastKnownMileage !== null)
            <p id="mileage_in-hint" class="mt-1 text-sm text-text-muted tabular-nums">
                {{ __('jobs.form.last_known_mileage', ['mileage' => number_format($lastKnownMileage)]) }}
            </p>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <x-ui.button type="submit">
            {{ $fromAppointment ? __('jobs.form.submit_from_appointment') : __('jobs.form.submit_walk_in') }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="$fromAppointment ? route('appointments.index', ['date' => $appointment->scheduled_at->format('Y-m-d')]) : route('customers.index')">
            {{ __('jobs.form.back') }}
        </x-ui.button>
    </div>
</form>
