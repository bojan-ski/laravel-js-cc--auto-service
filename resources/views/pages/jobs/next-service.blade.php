@php
    $title = __('vehicles.next_service.title', ['plate' => $vehicle->registration_plate]);
    $completed = $job->completed_at?->isoFormat('D MMM YYYY');

    $context = $job->mileage_in === null
        ? __('vehicles.next_service.context', ['reference' => $job->reference, 'date' => $completed])
        : __('vehicles.next_service.context_mileage', [
            'reference' => $job->reference,
            'date' => $completed,
            'mileage' => number_format($job->mileage_in),
        ]);
@endphp

<x-layouts.app :title="$title">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>

    <p class="mt-1 text-sm text-text-muted tabular-nums">{{ $context }}</p>

    <form method="POST" action="{{ route('jobs.next-service.update', $job) }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <x-vehicles.service-dates :vehicle="$vehicle" />

        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit">{{ __('vehicles.next_service.submit') }}</x-ui.button>

            <x-ui.button variant="secondary" :href="route('jobs.show', $job)">{{ __('app.actions.cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
