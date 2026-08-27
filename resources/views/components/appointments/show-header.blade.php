@props(['appointment'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tabular-nums">
            {{ $appointment->scheduled_at->isoFormat('ddd D MMM HH:mm') }}
        </h1>

        <p class="mt-1 text-text-muted">
            @if ($appointment->vehicle->trashed())
                <span class="font-medium tabular-nums">{{ $appointment->vehicle->registration_plate }}</span>
                <span class="text-sm">({{ __('app.deleted') }})</span>
            @else
                <a
                    href="{{ route('vehicles.show', $appointment->vehicle) }}"
                    class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
                >
                    {{ $appointment->vehicle->registration_plate }}
                </a>
            @endif

            <span aria-hidden="true">&middot;</span>
            {{ $appointment->vehicle->make }} {{ $appointment->vehicle->model }}
        </p>
    </div>

    <x-ui.status-badge :status="$appointment->status" />
</div>
