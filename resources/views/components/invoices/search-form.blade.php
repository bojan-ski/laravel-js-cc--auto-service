@props(['filters'])

<form
    method="GET"
    action="{{ route('invoices.index') }}"
    role="search"
    aria-label="{{ __('invoices.search_label') }}"
    class="mt-4 flex flex-wrap items-end gap-2"
>
    @if ($filters['status'] !== '')
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
    @endif

    <div class="min-w-64 flex-1">
        <x-forms.field
            name="search"
            type="search"
            :label="__('invoices.search_label')"
            :value="$filters['search']"
            :placeholder="__('invoices.search_placeholder')"
        />
    </div>

    <x-ui.button type="submit" variant="secondary">{{ __('app.actions.search') }}</x-ui.button>

    @if ($filters['search'] !== '')
        <x-ui.button variant="secondary" :href="route('invoices.index', array_filter(['status' => $filters['status']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    @endif
</form>
