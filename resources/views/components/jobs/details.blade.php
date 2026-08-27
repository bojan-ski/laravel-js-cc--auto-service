@props(['job'])

@php
    $mileage = $job->mileage_in === null
        ? null
        : __('jobs.show.mileage_value', ['mileage' => number_format($job->mileage_in)]);

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

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.complaint') }}
        </dt>

        <dd class="whitespace-pre-line">{{ $job->complaint }}</dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('jobs.show.mileage_in') }}
        </dt>

        <dd @class(['tabular-nums', 'text-text-muted' => $mileage === null])>
            <x-ui.value :value="$mileage" />
        </dd>

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
