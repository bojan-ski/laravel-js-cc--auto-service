@props(['filters', 'hasAny'])

@if (! $hasAny)
    <x-ui.empty-state
        class="mt-4"
        :title="__('invoices.empty.none.title')"
        :body="__('invoices.empty.none.body')"
    />
@elseif ($filters['search'] !== '')
    <x-ui.empty-state
        class="mt-4"
        :title="__('invoices.empty.no_matches.title')"
        :body="__('invoices.empty.no_matches.body')"
    >
        <x-ui.button variant="secondary" :href="route('invoices.index', array_filter(['status' => $filters['status']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        class="mt-4"
        :title="__('invoices.empty.none_in_status.title')"
        :body="__('invoices.empty.none_in_status.body')"
    >
        <x-ui.button variant="secondary" :href="route('invoices.index', array_filter(['search' => $filters['search']]))">
            {{ __('invoices.empty.none_in_status.clear') }}
        </x-ui.button>
    </x-ui.empty-state>
@endif
