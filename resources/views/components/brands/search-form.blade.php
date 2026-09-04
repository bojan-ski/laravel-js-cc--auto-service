@props(['search'])

<form
    method="GET"
    action="{{ route('admin.brands.index') }}"
    role="search"
    aria-label="{{ __('brands.search_label') }}"
    class="mt-4 flex flex-wrap items-end gap-2"
>
    <div class="min-w-64 flex-1">
        <x-forms.field
            name="search"
            type="search"
            :label="__('brands.search_label')"
            :value="$search"
            :placeholder="__('brands.search_placeholder')"
        />
    </div>

    <x-ui.button type="submit" variant="secondary">{{ __('app.actions.search') }}</x-ui.button>

    @if ($search !== '')
        <x-ui.button variant="secondary" :href="route('admin.brands.index')">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    @endif
</form>
