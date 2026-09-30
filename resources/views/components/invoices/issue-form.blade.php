@props(['job', 'totals', 'estimate' => null])

<form method="POST" action="{{ route('jobs.invoice.store', $job) }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    <p class="text-text-muted">
        <x-vehicles.plate :vehicle="$job->vehicle" />

        <span aria-hidden="true">&middot;</span>
        {{ $job->vehicle->make }} {{ $job->vehicle->model }}
    </p>

    <div class="text-sm text-text-muted">
        <h2 class="text-xs font-medium uppercase tracking-wider">{{ __('invoices.issue.bill_to') }}</h2>

        <p class="mt-1 text-base text-text"><x-vehicles.owner :customer="$job->customer" /></p>

        @if (filled($job->customer->address))
            <p>{{ $job->customer->address }}</p>
        @endif

        @if ($job->customer->isCompany())
            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3">
                <dt>{{ __('invoices.buyer.tax_id') }}</dt>
                <dd class="tabular-nums">{{ $job->customer->tax_id }}</dd>

                <dt>{{ __('invoices.buyer.registration_number') }}</dt>
                <dd class="tabular-nums">{{ $job->customer->registration_number }}</dd>
            </dl>
        @endif

        <p class="mt-2">{{ __('invoices.issue.bill_to_hint') }}</p>
    </div>

    @if ($estimate['warning'] ?? null)
        <x-estimates.issue-note :summary="$estimate" />
    @endif

    <x-invoices.totals :totals="$totals" />

    <x-forms.field
        name="due_at"
        type="date"
        :label="__('invoices.issue.due_at')"
        class="tabular-nums"
    />

    <div id="issue-warning" class="border border-status-unpaid bg-status-unpaid/10 p-4" role="note">
        <p class="font-semibold">{{ __('invoices.issue.warning_title') }}</p>
        <p class="mt-1 text-sm text-text">{{ __('invoices.issue.warning_body') }}</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-ui.button
            type="submit"
            aria-describedby="issue-warning"
            :aria-label="__('invoices.issue.submit_for', ['reference' => $job->reference])"
        >
            {{ __('invoices.issue.submit') }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('jobs.show', $job)">
            {{ __('invoices.issue.cancel') }}
        </x-ui.button>
    </div>
</form>
