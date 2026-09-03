<x-ui.empty-state
    class="mt-4"
    :title="__('staff.empty.title')"
    :body="__('staff.empty.body')"
>
    <x-ui.button variant="secondary" :href="route('admin.users.index')">
        {{ __('staff.empty.clear') }}
    </x-ui.button>
</x-ui.empty-state>
