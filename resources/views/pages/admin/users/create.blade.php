<x-layouts.app :title="__('staff.create.title')">
    <h1 class="text-2xl font-bold">{{ __('staff.create.title') }}</h1>

    <x-staff.form
        :action="route('admin.users.store')"
        :submit="__('staff.create.submit')"
        :cancel="route('admin.users.index')"
    />
</x-layouts.app>
