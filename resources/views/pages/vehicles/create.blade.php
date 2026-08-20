<x-layouts.app :title="__('vehicles.create.title')">
    <h1 class="text-2xl font-bold">{{ __('vehicles.create.title') }}</h1>

    <p class="mt-1 text-sm text-text-muted">{{ __('vehicles.create.for', ['name' => $customer->name]) }}</p>

    <x-vehicles.form
        :action="route('customers.vehicles.store', $customer)"
        method="POST"
        :owner="$customer"
        :submit="__('vehicles.create.submit')"
        :cancel="route('customers.show', $customer)"
    />
</x-layouts.app>
