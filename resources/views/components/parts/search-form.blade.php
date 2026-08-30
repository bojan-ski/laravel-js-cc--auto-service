@props(['filters'])

<form
    method="GET"
    action="{{ route('parts.index') }}"
    role="search"
    aria-label="{{ __('parts.search_label') }}"
    class="mt-4 flex flex-wrap items-end gap-2"
>
    @if ($filters['filter'] !== '')
        <input type="hidden" name="filter" value="{{ $filters['filter'] }}">
    @endif

    @if ($filters['trashed'] !== '')
        <input type="hidden" name="trashed" value="{{ $filters['trashed'] }}">
    @endif

    <div class="min-w-64 flex-1">
        <x-forms.field
            name="search"
            type="search"
            :label="__('parts.search_label')"
            :value="$filters['search']"
            :placeholder="__('parts.search_placeholder')"
        />
    </div>

    <x-ui.button type="submit" variant="secondary">{{ __('app.actions.search') }}</x-ui.button>

    @if ($filters['search'] !== '')
        <x-ui.button variant="secondary" :href="route('parts.index', array_filter(['filter' => $filters['filter'], 'trashed' => $filters['trashed']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    @endif
</form>
