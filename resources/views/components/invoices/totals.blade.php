@props(['totals'])

<dl class="border border-border">
    <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
        <dt class="text-sm text-text-soft">{{ __('invoices.issue.subtotal') }}</dt>
        <dd><x-ui.money :value="$totals['subtotal']" /></dd>
    </div>

    <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
        <dt class="text-sm text-text-soft">{{ __('invoices.issue.vat', ['rate' => $totals['rate']]) }}</dt>
        <dd><x-ui.money :value="$totals['tax']" /></dd>
    </div>

    <div class="flex items-baseline justify-between bg-surface-muted px-3 py-2">
        <dt class="text-sm font-semibold">{{ __('invoices.issue.total') }}</dt>
        <dd class="font-semibold"><x-ui.money :value="$totals['total']" /></dd>
    </div>
</dl>
