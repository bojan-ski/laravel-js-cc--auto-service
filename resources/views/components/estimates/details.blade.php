@props(['estimate'])

@php
    $job = $estimate->job;
    $label = 'text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5';
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('estimates.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="{{ $label }}">{{ __('estimates.show.job') }}</dt>

        <dd>
            <a
                href="{{ route('jobs.show', $job) }}"
                class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
            >
                {{ $job->reference }}
            </a>
        </dd>

        <dt class="{{ $label }}">{{ __('estimates.show.customer') }}</dt>

        <dd><x-vehicles.owner :customer="$job->customer" /></dd>

        <dt class="{{ $label }}">{{ __('estimates.show.vehicle') }}</dt>

        <dd>
            <x-vehicles.plate :vehicle="$job->vehicle" />

            <span aria-hidden="true">&middot;</span>
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
        </dd>

        <dt class="{{ $label }}">{{ __('estimates.show.created') }}</dt>

        <dd class="tabular-nums">{{ $estimate->created_at->isoFormat('D MMM YYYY HH:mm') }}</dd>

        <dt class="{{ $label }}">{{ __('estimates.show.created_by') }}</dt>

        <dd>
            {{ $estimate->createdBy->name }}

            @if ($estimate->createdBy->trashed())
                <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
            @endif
        </dd>

        @if ($estimate->wasDecided())
            <dt class="{{ $label }}">{{ __('estimates.show.decided') }}</dt>

            <dd class="tabular-nums">{{ $estimate->decided_at->isoFormat('D MMM YYYY HH:mm') }}</dd>

            <dt class="{{ $label }}">{{ __('estimates.show.decided_by') }}</dt>

            <dd>
                {{ $estimate->decidedBy->name }}

                @if ($estimate->decidedBy->trashed())
                    <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
                @endif
            </dd>

            <dt class="{{ $label }}">{{ __('estimates.show.decision_method') }}</dt>

            <dd>{{ $estimate->decision_method->label() }}</dd>

            @if (filled($estimate->decision_note))
                <dt class="{{ $label }}">{{ __('estimates.show.decision_note') }}</dt>

                <dd class="whitespace-pre-line wrap-break-word">{{ $estimate->decision_note }}</dd>
            @endif
        @endif

        @if ($estimate->isSuperseded())
            <dt class="{{ $label }}">{{ __('estimates.show.superseded') }}</dt>

            <dd class="tabular-nums">{{ $estimate->superseded_at->isoFormat('D MMM YYYY HH:mm') }}</dd>
        @endif

        @if ($estimate->emailed_at !== null)
            <dt class="{{ $label }}">{{ __('estimates.show.emailed') }}</dt>

            <dd class="tabular-nums">{{ $estimate->emailed_at->isoFormat('D MMM YYYY HH:mm') }}</dd>
        @endif
    </dl>
</section>
