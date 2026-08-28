@props(['appointment'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tabular-nums">
            {{ $appointment->scheduled_at->isoFormat('ddd D MMM HH:mm') }}
        </h1>

        <p class="mt-1 text-text-muted">
            <x-vehicles.plate :vehicle="$appointment->vehicle" />

            <span aria-hidden="true">&middot;</span>
            {{ $appointment->vehicle->make }} {{ $appointment->vehicle->model }}
        </p>
    </div>

    <x-ui.status-badge :status="$appointment->status" />
</div>
