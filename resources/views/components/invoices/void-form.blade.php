@props(['invoice', 'totals'])

@php
    $job = $invoice->job;

    $paidNotice = $invoice->isPaid()
        ? __('invoices.void.paid_notice', ['date' => $invoice->paid_at->isoFormat('D MMM YYYY')])
        : null;
@endphp

<form method="POST" action="{{ route('invoices.void', $invoice) }}" class="mt-4 max-w-2xl space-y-4">
    @csrf
    @method('PATCH')

    <p>
        <span class="text-sm text-text-muted">{{ __('invoices.show.job') }}</span>

        <a
            href="{{ route('jobs.show', $job) }}"
            class="font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
        >
            {{ $job->reference }}
        </a>
    </p>

    <x-invoices.totals :totals="$totals" />

    <div id="void-warning" class="border border-danger bg-danger/10 p-4" role="note">
        <p class="font-semibold">
            {{ __('invoices.void.warning_title', ['reference' => $job->reference]) }}
        </p>

        <p class="mt-1 text-sm text-text">{{ __('invoices.void.warning_body') }}</p>

        @if ($paidNotice !== null)
            <p class="mt-2 text-sm font-medium text-text">{{ $paidNotice }}</p>
        @endif

        <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-text">
            <li>{{ __('invoices.void.consequences.kept') }}</li>
            <li>{{ __('invoices.void.consequences.number', ['number' => $invoice->number]) }}</li>
            <li>{{ __('invoices.void.consequences.stock') }}</li>
            <li>{{ __('invoices.void.consequences.reissue') }}</li>
        </ul>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-ui.button
            type="submit"
            variant="danger"
            aria-describedby="void-warning"
            :aria-label="__('invoices.void.action_for', ['number' => $invoice->number])"
        >
            {{ __('invoices.void.action') }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('invoices.show', $invoice)">
            {{ __('invoices.void.cancel') }}
        </x-ui.button>
    </div>
</form>
