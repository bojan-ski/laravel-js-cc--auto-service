<x-layouts.app :title="__('jobs.board.title')">
    <x-jobs.index-header />

    <x-jobs.status-tabs :filters="$filters" :counts="$counts" />

    <div class="mt-4 flex flex-wrap items-end gap-2">
        <x-jobs.search-form :filters="$filters" />
        <x-jobs.mine-toggle :filters="$filters" />
    </div>

    @if ($jobs->total() > 0)
        <x-jobs.table :jobs="$jobs" :filters="$filters" />
    @else
        <x-jobs.index-empty :filters="$filters" :has-any="$hasAny" />
    @endif
</x-layouts.app>
