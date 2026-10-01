@props(['revenue'])

<section class="border border-border bg-surface">
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('reports.revenue.title') }}</h2>

    <dl>
        <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
            <dt class="text-sm text-text-soft">{{ __('reports.revenue.invoices') }}</dt>
            <dd class="tabular-nums">{{ $revenue['invoices'] }}</dd>
        </div>

        @if ($revenue['has_discount'])
            <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
                <dt class="text-sm text-text-soft">{{ __('reports.revenue.before_discount') }}</dt>
                <dd><x-ui.money :value="$revenue['before_discount']" /></dd>
            </div>

            <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
                <dt class="text-sm text-text-soft">{{ __('reports.revenue.discount') }}</dt>
                <dd><x-ui.money :value="$revenue['discount']" /></dd>
            </div>
        @endif

        <div class="flex items-baseline justify-between border-b border-border px-3 py-2">
            <dt class="text-sm text-text-soft">{{ __('reports.revenue.subtotal') }}</dt>
            <dd><x-ui.money :value="$revenue['subtotal']" /></dd>
        </div>

        <div class="flex items-baseline justify-between border-b border-border-strong px-3 py-2">
            <dt class="text-sm text-text-soft">{{ __('reports.revenue.vat') }}</dt>
            <dd><x-ui.money :value="$revenue['vat']" /></dd>
        </div>

        <div class="flex items-baseline justify-between bg-surface-muted px-3 py-2">
            <dt class="text-sm font-semibold">{{ __('reports.revenue.total') }}</dt>
            <dd class="font-semibold"><x-ui.money :value="$revenue['total']" /></dd>
        </div>
    </dl>

    @if ($revenue['voided'] > 0)
        <p class="border-t border-border px-3 py-2 text-sm text-text-muted">{{ trans_choice('reports.voided', $revenue['voided'], ['count' => $revenue['voided']]) }}</p>
    @endif
</section>
