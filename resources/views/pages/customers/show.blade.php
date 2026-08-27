<x-layouts.app :title="$customer->name">
    <x-customers.show-header :customer="$customer" />

    <x-customers.contact-details :customer="$customer" />

    <x-vehicles.list-section :customer="$customer" />

    @can('viewDeleted', App\Models\Vehicle::class)
        @if ($customer->deletedVehicles->isNotEmpty())
            <x-vehicles.deleted-section :customer="$customer" />
        @endif
    @endcan

    <x-customers.job-history :customer="$customer" :jobs="$jobHistory" :total="$jobTotal" />
</x-layouts.app>
