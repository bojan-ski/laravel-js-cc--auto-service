<x-layouts.app :title="__('invoices.void.page_title', ['number' => $invoice->number])">
    <h1 class="text-2xl font-bold">
        {{ __('invoices.void.heading') }}
        <span class="tabular-nums">{{ $invoice->number }}</span>
    </h1>

    <x-invoices.void-form :invoice="$invoice" :totals="$totals" />
</x-layouts.app>
