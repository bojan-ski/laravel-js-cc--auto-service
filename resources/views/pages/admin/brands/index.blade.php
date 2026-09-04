<x-layouts.app :title="__('brands.title')">
    <x-brands.index-header />

    <x-brands.search-form :search="$search" />

    @if ($brands->total() > 0)
        <x-brands.table :brands="$brands" />
    @else
        <x-brands.index-empty :has-any="$hasAny" />
    @endif
</x-layouts.app>
