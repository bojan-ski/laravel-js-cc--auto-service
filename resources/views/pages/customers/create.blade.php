<x-layouts.app :title="__('customers.create.title')">
    <h1 class="text-2xl font-bold">{{ __('customers.create.title') }}</h1>

    <x-customers.form
        :action="route('customers.store')"
        method="POST"
        :submit="__('customers.create.submit')"
        :cancel="route('customers.index')"
    />
</x-layouts.app>
