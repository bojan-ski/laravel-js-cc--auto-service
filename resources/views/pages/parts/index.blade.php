<x-layouts.app :title="__('parts.title')">
    <x-parts.index-header />

    <x-parts.filter-tabs :filters="$filters" :counts="$counts" />

    <x-parts.search-form :filters="$filters" />

    @if ($parts->total() > 0)
        <x-parts.table :parts="$parts" :with-trashed="$withTrashed" />
    @else
        <x-parts.index-empty :filters="$filters" :has-any="$hasAny" />
    @endif
</x-layouts.app>
