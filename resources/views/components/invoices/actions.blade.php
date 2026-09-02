@props(['invoice'])

<div class="mt-4 flex flex-wrap items-center gap-2 print:hidden">
    <h2 class="sr-only">{{ __('invoices.show.actions') }}</h2>

    @can('markPaid', $invoice)
        <x-ui.action-form
            variant="primary"
            :action="route('invoices.paid', $invoice)"
            :confirm="__('invoices.show.confirm_paid', ['number' => $invoice->number])"
            :aria-label="__('invoices.show.mark_paid_for', ['number' => $invoice->number])"
        >
            {{ __('invoices.show.mark_paid') }}
        </x-ui.action-form>
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
