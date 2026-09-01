@props(['invoices'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('invoices.count', $invoices->total(), ['count' => $invoices->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-invoices.table-head />

        <tbody role="rowgroup">
            @foreach ($invoices as $invoice)
                <x-invoices.row :invoice="$invoice" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $invoices->links() }}</div>
