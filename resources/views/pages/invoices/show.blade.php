<x-layouts.app :title="__('invoices.show.title', ['number' => $invoice->number])">
    <x-invoices.identity :invoice="$invoice" />

    <x-invoices.show-header :invoice="$invoice" />

    <x-invoices.actions :invoice="$invoice" />

    <x-invoices.details :invoice="$invoice" />

    @if ($invoice->isVoid())
        <x-invoices.void-notice :invoice="$invoice" />
    @else
        <x-invoices.line-items :items="$lineItems" />
    @endif

    <div class="mt-4 flex justify-end">
        <div class="w-full sm:max-w-sm">
            <x-invoices.totals :totals="$totals" />
        </div>
    </div>
</x-layouts.app>
