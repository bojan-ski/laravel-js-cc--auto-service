<x-layouts.app :title="__('customers.title')">
    <x-customers.index-header />

    <x-customers.search-form :search="$filters['search']" />

    @if ($customers->total() > 0)
        <x-customers.table :customers="$customers" />
    @else
        <x-customers.index-empty :search="$filters['search']" />
    @endif
</x-layouts.app>
