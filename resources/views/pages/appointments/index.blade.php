<x-layouts.app :title="__('appointments.title')">
    <x-appointments.index-header :hours="$hours" />

    <x-appointments.toolbar :filters="$filters" />

    @if ($appointments->total() > 0)
        <x-appointments.table :groups="$groups" :appointments="$appointments" :filters="$filters" />
    @else
        <x-appointments.index-empty :filters="$filters" :has-any="$hasAny" />
    @endif
</x-layouts.app>
