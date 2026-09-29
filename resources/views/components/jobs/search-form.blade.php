@props(['filters'])

<form
    method="GET"
    action="{{ route('jobs.index') }}"
    role="search"
    aria-label="{{ __('jobs.board.search_label') }}"
    class="flex w-full flex-wrap items-end gap-2 md:w-auto md:flex-1"
>
    @if ($filters['status'] !== '')
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
    @endif
    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
    @if ($filters['mine'])
        <input type="hidden" name="mine" value="1">
    @endif

    <div class="min-w-64 flex-1">
        <x-forms.field
            name="search"
            type="search"
            :label="__('jobs.board.search_label')"
            :value="$filters['search']"
            :placeholder="__('jobs.board.search_placeholder')"
        />
    </div>

    <x-ui.button type="submit" variant="secondary">{{ __('app.actions.search') }}</x-ui.button>

    @if ($filters['search'] !== '')
        <x-ui.button variant="secondary" :href="route('jobs.index', array_filter(['status' => $filters['status'], 'sort' => $filters['sort'], 'direction' => $filters['direction'], 'mine' => $filters['mine']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    @endif
</form>
