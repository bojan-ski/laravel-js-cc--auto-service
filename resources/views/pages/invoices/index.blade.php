<x-layouts.app :title="__('invoices.title')">
    <x-invoices.index-header />

    <x-invoices.status-tabs :filters="$filters" :counts="$counts" />

    <x-invoices.search-form :filters="$filters" />

    @if ($invoices->total() > 0)
        <x-invoices.table :invoices="$invoices" />
    @else
        <x-invoices.index-empty :filters="$filters" :has-any="$hasAny" />
    @endif
</x-layouts.app>
