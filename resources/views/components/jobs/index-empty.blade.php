@props(['filters', 'hasAny'])

@if (! $hasAny)
    <x-ui.empty-state
        class="mt-4"
        :title="__('jobs.board.empty.none.title')"
        :body="__('jobs.board.empty.none.body')"
    >
        @can('create', App\Models\Job::class)
            <x-ui.button :href="route('jobs.create')">{{ __('jobs.board.new') }}</x-ui.button>
        @endcan
    </x-ui.empty-state>
@elseif ($filters['search'] !== '')
    <x-ui.empty-state
        class="mt-4"
        :title="__('jobs.board.empty.no_matches.title')"
        :body="__('jobs.board.empty.no_matches.body')"
    >
        <x-ui.button variant="secondary" :href="route('jobs.index', array_filter(['status' => $filters['status'], 'sort' => $filters['sort'], 'direction' => $filters['direction']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        class="mt-4"
        :title="__('jobs.board.empty.none_in_status.title')"
        :body="__('jobs.board.empty.none_in_status.body')"
    >
        <x-ui.button variant="secondary" :href="route('jobs.index', array_filter(['search' => $filters['search'], 'sort' => $filters['sort'], 'direction' => $filters['direction']]))">
            {{ __('jobs.board.empty.none_in_status.clear') }}
        </x-ui.button>
    </x-ui.empty-state>
@endif
