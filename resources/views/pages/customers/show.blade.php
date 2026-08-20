<x-layouts.app :title="$customer->name">
    <x-customers.show-header :customer="$customer" />

    <x-customers.contact-details :customer="$customer" />

    <x-vehicles.list-section :customer="$customer" />

    @can('viewDeleted', App\Models\Vehicle::class)
        @if ($customer->deletedVehicles->isNotEmpty())
            <x-vehicles.deleted-section :customer="$customer" />
        @endif
    @endcan

    <x-customers.placeholder-section
        :heading="__('customers.show.jobs')"
        :message="__('customers.show.jobs_empty')"
    />
</x-layouts.app>
