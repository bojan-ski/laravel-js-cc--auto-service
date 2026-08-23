<nav class="mt-4 flex flex-wrap gap-1" aria-label="{{ __('jobs.board.tabs_label') }}">
    @foreach ($tabs as $tab)
        <x-jobs.filter-link :item="$tab" />
    @endforeach
</nav>
