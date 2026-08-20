<x-layouts.app :title="$customer->name">
    <x-customers.show-header :customer="$customer" />

    <x-customers.contact-details :customer="$customer" />

    <x-customers.placeholder-section
        :heading="__('customers.show.vehicles')"
        :message="__('customers.show.vehicles_empty')"
    />

    <x-customers.placeholder-section
        :heading="__('customers.show.jobs')"
        :message="__('customers.show.jobs_empty')"
    />
</x-layouts.app>
