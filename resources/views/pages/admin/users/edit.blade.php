<x-layouts.app :title="__('staff.edit.title', ['name' => $member->name])">
    <h1 class="text-2xl font-bold">{{ __('staff.edit.title', ['name' => $member->name]) }}</h1>

    <x-staff.form
        method="PUT"
        :action="route('admin.users.update', $member)"
        :member="$member"
        :locked-role="$lockedRole"
        :submit="__('staff.edit.submit')"
        :cancel="route('admin.users.index')"
    />
</x-layouts.app>
