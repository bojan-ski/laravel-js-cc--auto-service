@props(['job'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tabular-nums">{{ $job->reference }}</h1>

        <p class="mt-1 text-text-muted">
            <x-vehicles.plate :vehicle="$job->vehicle" />

            <span aria-hidden="true">&middot;</span>
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
            <span aria-hidden="true">&middot;</span>
            <x-vehicles.owner :customer="$job->customer" />
        </p>
    </div>

    <x-ui.status-badge :status="$job->status" />
</div>
