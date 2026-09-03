<x-layouts.app :title="__('staff.title')">
    <x-staff.index-header />

    <x-staff.filter-tabs :filters="$filters" :counts="$counts" />

    <x-staff.search-form :filters="$filters" />

    @if ($members->total() > 0)
        <x-staff.table :members="$members" :current-user-id="$currentUserId" />
    @else
        <x-staff.index-empty />
    @endif
</x-layouts.app>
