<x-layouts.app :title="__('estimates.show.title', ['number' => $estimate->number])">
    <x-estimates.identity :estimate="$estimate" />

    <x-estimates.show-header :estimate="$estimate" />

    <x-estimates.actions :estimate="$estimate" :can-email="$canEmail" />

    <x-estimates.details :estimate="$estimate" />

    @if ($estimate->isSuperseded())
        <x-estimates.superseded-notice :estimate="$estimate" />
    @endif

    <x-invoices.line-items :items="$estimate->lines" :show-discount="$estimate->hasDiscount()" />

    <div class="mt-4 flex justify-end">
        <div class="w-full sm:max-w-sm">
            <x-invoices.totals :totals="$totals" />
        </div>
    </div>

    @if ($history->isNotEmpty())
        <x-estimates.history :history="$history" :current="$estimate" />
    @endif
</x-layouts.app>
