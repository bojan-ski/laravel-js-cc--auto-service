@props(['invoice'])

@php
    $job = $invoice->job;
    $label = 'text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5';
    $due = $invoice->due_at?->isoFormat('D MMM YYYY');
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('invoices.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="{{ $label }}">{{ __('invoices.show.job') }}</dt>

        <dd>
            <a
                href="{{ route('jobs.show', $job) }}"
                class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
            >
                {{ $job->reference }}
            </a>
        </dd>

        <dt class="{{ $label }}">{{ __('invoices.show.customer') }}</dt>

        <dd><x-vehicles.owner :customer="$job->customer" /></dd>

        <dt class="{{ $label }}">{{ __('invoices.show.vehicle') }}</dt>

        <dd>
            <x-vehicles.plate :vehicle="$job->vehicle" />

            <span aria-hidden="true">&middot;</span>
            {{ $job->vehicle->make }} {{ $job->vehicle->model }}
        </dd>

        <dt class="{{ $label }}">{{ __('invoices.show.issued') }}</dt>

        <dd class="tabular-nums">{{ $invoice->issued_at->isoFormat('D MMM YYYY') }}</dd>

        <dt class="{{ $label }}">{{ __('invoices.show.issued_by') }}</dt>

        <dd>
            {{ $invoice->issuedBy->name }}

            @if ($invoice->issuedBy->trashed())
                <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
            @endif
        </dd>

        <dt class="{{ $label }}">{{ __('invoices.show.due') }}</dt>

        <dd @class(['tabular-nums', 'text-text-muted' => $due === null])>
            <x-ui.value :value="$due" />
        </dd>
    </dl>
</section>
