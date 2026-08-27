@props(['appointment'])

@php
    $timestamps = array_filter([
        'arrived' => $appointment->arrived_at,
        'no_show' => $appointment->no_show_at,
        'cancelled' => $appointment->cancelled_at,
    ]);
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('appointments.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('appointments.show.customer') }}
        </dt>

        <dd>
            <x-vehicles.owner :customer="$appointment->customer" />
        </dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('appointments.show.duration') }}
        </dt>

        <dd class="tabular-nums">
            {{ __('appointments.show.duration_value', ['minutes' => $appointment->duration_minutes]) }}
        </dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('appointments.show.booked_by') }}
        </dt>

        <dd>
            {{ $appointment->createdBy->name }}

            @if ($appointment->createdBy->trashed())
                <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
            @endif
        </dd>

        <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
            {{ __('appointments.show.notes') }}
        </dt>

        <dd @class(['whitespace-pre-line', 'text-text-muted' => $appointment->notes === null])>
            <x-ui.value :value="$appointment->notes" />
        </dd>

        @foreach ($timestamps as $key => $value)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('appointments.show.'.$key) }}
            </dt>

            <dd class="tabular-nums">{{ $value->isoFormat('D MMM HH:mm') }}</dd>
        @endforeach

        @if ($appointment->job)
            <dt class="text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5">
                {{ __('appointments.show.job') }}
            </dt>

            <dd>
                <a
                    href="{{ route('jobs.show', $appointment->job) }}"
                    class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
                >
                    {{ $appointment->job->reference }}
                </a>
            </dd>
        @endif
    </dl>
</section>
