@props(['invoice'])

<div class="mt-4 flex flex-wrap items-end gap-2 print:hidden">
    <h2 class="sr-only">{{ __('invoices.show.actions') }}</h2>

    @can('markPaid', $invoice)
        <x-invoices.mark-paid-form :invoice="$invoice" />
    @endcan

    @can('void', $invoice)
        <x-ui.button
            variant="danger"
            :href="route('invoices.void.create', $invoice)"
            :aria-label="__('invoices.void.action_for', ['number' => $invoice->number])"
        >
            {{ __('invoices.void.action') }}
        </x-ui.button>
    @endcan

    <span data-print-slot data-print-label="{{ __('invoices.show.print') }}"></span>
</div>
