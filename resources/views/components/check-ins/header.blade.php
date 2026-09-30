@props(['job'])

@php
    $mileage = $job->mileage_in === null
        ? null
        : __('jobs.show.mileage_value', ['mileage' => number_format($job->mileage_in)]);
@endphp

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tabular-nums">{{ __('check_ins.show.title', ['reference' => $job->reference]) }}</h1>

        <p class="mt-1 text-text-muted">
            <x-vehicles.plate :vehicle="$job->vehicle" />

            <span aria-hidden="true">&middot;</span>
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
            <span aria-hidden="true">&middot;</span>
            <x-vehicles.owner :customer="$job->customer" />
        </p>

        <p class="mt-1 text-sm text-text-soft tabular-nums">
            {{ __('check_ins.fields.mileage_in') }}
            <x-ui.value :value="$mileage" />
            <span aria-hidden="true">&middot;</span>
            {{ __('check_ins.fields.received') }} {{ $job->created_at->isoFormat('D MMM HH:mm') }}
        </p>

        @if ($job->checkIn)
            <p class="mt-1 text-sm text-text-soft">
                {{ __('check_ins.show.recorded_by', ['name' => $job->checkIn->recordedBy->name]) }}

                @if ($job->checkIn->recordedBy->trashed())
                    ({{ __('app.deleted') }})
                @endif
            </p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if ($job->checkIn)
            <x-ui.button variant="secondary" :href="route('jobs.check-in.print', $job)">{{ __('check_ins.show.print') }}</x-ui.button>
        @endif

        <a href="{{ route('jobs.show', $job) }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">
            {{ __('check_ins.show.back', ['reference' => $job->reference]) }}
        </a>
    </div>
</div>
