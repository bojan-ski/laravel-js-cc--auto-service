@props(['job'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tabular-nums">{{ $job->reference }}</h1>

        <p class="mt-1 text-text-muted">
            @if ($job->vehicle->trashed())
                <span class="font-medium tabular-nums">{{ $job->vehicle->registration_plate }}</span>
                <span class="text-sm">({{ __('app.deleted') }})</span>
            @else
                <a
                    href="{{ route('vehicles.show', $job->vehicle) }}"
                    class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
                >
                    {{ $job->vehicle->registration_plate }}
                </a>
            @endif

            <span aria-hidden="true">&middot;</span>
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
            <span aria-hidden="true">&middot;</span>
            <x-vehicles.owner :customer="$job->customer" />
        </p>
    </div>

    <x-ui.status-badge :status="$job->status" />
</div>
