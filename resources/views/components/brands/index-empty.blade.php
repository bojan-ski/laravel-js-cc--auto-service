@props(['hasAny'])

@if ($hasAny)
    <x-ui.empty-state
        class="mt-4"
        :title="__('brands.empty.no_matches.title')"
        :body="__('brands.empty.no_matches.body')"
    >
        <x-ui.button variant="secondary" :href="route('admin.brands.index')">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    </x-ui.empty-state>
@else
    <x-ui.empty-state
        class="mt-4"
        :title="__('brands.empty.none.title')"
        :body="__('brands.empty.none.body')"
    />
@endif
