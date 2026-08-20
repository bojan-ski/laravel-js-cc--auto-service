<x-layouts.app :title="__('customers.edit.title', ['name' => $customer->name])">
    <h1 class="text-2xl font-bold">{{ __('customers.edit.title', ['name' => $customer->name]) }}</h1>

    <x-customers.form
        :action="route('customers.update', $customer)"
        method="PUT"
        :customer="$customer"
        :submit="__('customers.edit.submit')"
        :cancel="route('customers.show', $customer)"
    />
</x-layouts.app>
