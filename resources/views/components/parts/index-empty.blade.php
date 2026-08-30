@props(['filters', 'hasAny'])

@if (! $hasAny)
    <x-ui.empty-state
        class="mt-4"
        :title="__('parts.empty.none.title')"
        :body="__('parts.empty.none.body')"
    >
        @can('create', App\Models\Part::class)
            <x-ui.button :href="route('parts.create')">{{ __('parts.new') }}</x-ui.button>
        @endcan
    </x-ui.empty-state>
@elseif ($filters['search'] !== '')
    <x-ui.empty-state
        class="mt-4"
        :title="__('parts.empty.no_matches.title')"
        :body="__('parts.empty.no_matches.body')"
    >
        <x-ui.button variant="secondary" :href="route('parts.index', array_filter(['filter' => $filters['filter'], 'trashed' => $filters['trashed']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        class="mt-4"
        :title="__('parts.empty.none_low.title')"
        :body="__('parts.empty.none_low.body')"
    >
        <x-ui.button variant="secondary" :href="route('parts.index', array_filter(['trashed' => $filters['trashed']]))">
            {{ __('parts.empty.none_low.clear') }}
        </x-ui.button>
    </x-ui.empty-state>
@endif
