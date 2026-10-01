@props(['job', 'estimate' => null, 'showEstimate' => false, 'showNextService' => false])

@php
    $mileage = $job->mileage_in === null
        ? null
        : __('jobs.show.mileage_value', ['mileage' => number_format($job->mileage_in)]);

    $nextService = $showNextService
        ? implode(' · ', array_filter([
            $job->vehicle->next_service_on?->isoFormat('D MMM YYYY'),
            $job->vehicle->next_service_mileage === null
                ? null
                : __('vehicles.show.mileage_value', ['mileage' => number_format($job->vehicle->next_service_mileage)]),
        ]))
        : '';

    $timestamps = array_filter([
        'received' => $job->created_at,
        'started' => $job->started_at,
        'awaiting_parts' => $job->awaiting_parts_at,
        'completed' => $job->completed_at,
        'cancelled' => $job->cancelled_at,
    ]);
@endphp

<section class="border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('jobs.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_minmax(0,1fr)] md:grid-cols-[8rem_minmax(0,1fr)_8rem_minmax(0,1fr)] xl:grid-cols-[8rem_minmax(0,1fr)]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.complaint') }}
        </dt>

        <dd class="whitespace-pre-line wrap-break-word md:col-span-3 xl:col-span-1">{{ $job->complaint }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.mileage_in') }}
        </dt>

        <dd @class(['tabular-nums', 'text-text-muted' => $mileage === null])>
            <x-ui.value :value="$mileage" />
        </dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('check_ins.summary.label') }}
        </dt>

        <dd>
            <x-check-ins.summary :job="$job" />
        </dd>

        @if ($showEstimate)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('estimates.summary.label') }}
            </dt>

            <dd>
                <x-estimates.summary :job="$job" :summary="$estimate" />
            </dd>
        @endif

        @if ($showNextService)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('vehicles.next_service.label') }}
            </dt>

            <dd>
                <span @class(['tabular-nums', 'text-text-muted' => $nextService === ''])><x-ui.value :value="$nextService" /></span>

                @unless ($job->vehicle->trashed())
                    <span aria-hidden="true">&middot;</span>
                    <a
                        href="{{ route('jobs.next-service.edit', $job) }}"
                        aria-label="{{ __('vehicles.next_service.set_for', ['plate' => $job->vehicle->registration_plate]) }}"
                        class="font-medium underline underline-offset-2 hover:text-brand-dark"
                    >{{ __('vehicles.next_service.set') }}</a>
                @endunless
            </dd>
        @endif

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.origin') }}
        </dt>

        <dd>
            @if ($job->appointment)
                <a
                    href="{{ route('appointments.show', $job->appointment) }}"
                    class="font-medium underline underline-offset-2 hover:text-brand-dark"
                >
                    {{ __('jobs.show.origin_booking', [
                        'time' => $job->appointment->scheduled_at->isoFormat('D MMM HH:mm'),
                    ]) }}
                </a>
            @else
                {{ __('jobs.show.walk_in') }}
            @endif
        </dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.opened_by') }}
        </dt>

        <dd>
            {{ $job->createdBy->name }}

            @if ($job->createdBy->trashed())
                <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
            @endif
        </dd>

        @foreach ($timestamps as $key => $value)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('jobs.show.'.$key) }}
            </dt>

            <dd class="tabular-nums">{{ $value->isoFormat('D MMM HH:mm') }}</dd>
        @endforeach
    </dl>
</section>
