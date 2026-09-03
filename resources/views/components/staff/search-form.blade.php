@props(['filters'])

<form
    method="GET"
    action="{{ route('admin.users.index') }}"
    role="search"
    aria-label="{{ __('staff.search_label') }}"
    class="mt-4 flex flex-wrap items-end gap-2"
>
    @if ($filters['role'] !== '')
        <input type="hidden" name="role" value="{{ $filters['role'] }}">
    @endif

    <div class="min-w-64 flex-1">
        <x-forms.field
            name="search"
            type="search"
            :label="__('staff.search_label')"
            :value="$filters['search']"
            :placeholder="__('staff.search_placeholder')"
        />
    </div>

    <x-ui.button type="submit" variant="secondary">{{ __('app.actions.search') }}</x-ui.button>

    @if ($filters['search'] !== '')
        <x-ui.button variant="secondary" :href="route('admin.users.index', array_filter(['role' => $filters['role']]))">
            {{ __('app.actions.clear_search') }}
        </x-ui.button>
    @endif
</form>
