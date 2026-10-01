<x-layouts.app :title="__('reports.title')">
    <h1 class="text-2xl font-bold">{{ __('reports.title') }}</h1>

    <x-reports.period-form :from="$from" :to="$to" />

    @if ($revenue['invoices'] === 0)
        <x-ui.empty-state class="mt-4" :title="__('reports.empty.title')" :body="__('reports.empty.body')" />

        @if ($revenue['voided'] > 0)
            <p class="mt-2 text-sm text-text-muted">{{ trans_choice('reports.voided', $revenue['voided'], ['count' => $revenue['voided']]) }}</p>
        @endif
    @else
        <div class="mt-4 grid grid-cols-1 items-start gap-3 xl:grid-cols-2">
            <x-reports.revenue :revenue="$revenue" />
            <x-reports.vat :rows="$vat" />
            <x-reports.payments :rows="$payments" />
            <x-reports.labor :rows="$labor" />
            <x-reports.parts :parts="$parts" :with-margin="$withMargin" class="xl:col-span-2" />
        </div>
    @endif
</x-layouts.app>
