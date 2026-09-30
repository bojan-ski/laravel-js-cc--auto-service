@props(['job'])

@php
    $checkIn = $job->checkIn;
    $mileage = $job->mileage_in === null
        ? null
        : __('jobs.show.mileage_value', ['mileage' => number_format($job->mileage_in)]);
@endphp

<section class="mt-6 print:break-inside-avoid">
    <h2 class="sr-only">{{ __('check_ins.form.heading') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[12rem_minmax(0,1fr)] print:grid-cols-[12rem_minmax(0,1fr)]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.vehicle') }}</dt>
        <dd>
            <x-vehicles.plate :vehicle="$job->vehicle" />
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
        </dd>

        @if (filled($job->vehicle->vin))
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.vin') }}</dt>
            <dd class="tabular-nums">{{ $job->vehicle->vin }}</dd>
        @endif

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.customer') }}</dt>
        <dd>{{ $job->customer->name }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.mileage_in') }}</dt>
        <dd class="tabular-nums"><x-ui.value :value="$mileage" /></dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.received') }}</dt>
        <dd class="tabular-nums">{{ $job->created_at->isoFormat('D MMM YYYY HH:mm') }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.fuel_level') }}</dt>
        <dd class="tabular-nums">{{ $checkIn->fuel_level->label() }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.damage_notes') }}</dt>
        <dd class="whitespace-pre-line wrap-break-word"><x-ui.value :value="$checkIn->damage_notes" /></dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('check_ins.fields.valuables') }}</dt>
        <dd class="whitespace-pre-line wrap-break-word"><x-ui.value :value="$checkIn->valuables" /></dd>
    </dl>
</section>
