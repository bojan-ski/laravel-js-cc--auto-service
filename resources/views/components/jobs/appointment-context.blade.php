@props(['appointment'])

<dl class="space-y-3">
    <div>
        <dt class="text-sm font-medium">{{ __('jobs.form.appointment') }}</dt>
        <dd class="mt-1 tabular-nums">
            {{ __('jobs.form.appointment_summary', [
                'time' => $appointment->scheduled_at->isoFormat('ddd D MMM HH:mm'),
                'name' => $appointment->createdBy->name,
            ]) }}
        </dd>
    </div>

    <div>
        <dt class="text-sm font-medium">{{ __('jobs.form.vehicle') }}</dt>
        <dd class="mt-1 tabular-nums">
            {{ __('vehicles.picker.option', [
                'plate' => $appointment->vehicle->registration_plate,
                'vehicle' => $appointment->vehicle->make.' '.$appointment->vehicle->model,
                'owner' => $appointment->customer->name,
            ]) }}
        </dd>
    </div>
</dl>
