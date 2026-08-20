@props(['search'])

@if ($search !== '')
    <x-ui.empty-state
        class="mt-4"
        :title="__('customers.empty.no_matches.title')"
        :body="__('customers.empty.no_matches.body')"
    >
        <x-ui.button variant="secondary" :href="route('customers.index')">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        class="mt-4"
        :title="__('customers.empty.none.title')"
        :body="__('customers.empty.none.body')"
    >
        @can('create', App\Models\Customer::class)
            <x-ui.button :href="route('customers.create')">{{ __('customers.index.new') }}</x-ui.button>
        @endcan
    </x-ui.empty-state>
@endif
