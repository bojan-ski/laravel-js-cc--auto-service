@props(['customer'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('vehicles.section.title') }}</h2>

        @can('create', App\Models\Vehicle::class)
            <x-ui.button
                :href="route('customers.vehicles.create', $customer)"
                :aria-label="__('vehicles.section.add_for', ['name' => $customer->name])"
            >
                {{ __('vehicles.section.add') }}
            </x-ui.button>
        @endcan
    </div>

    @if ($customer->vehicles->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('vehicles.section.empty')" :level="0" />
    @else
        <div class="mt-2 overflow-x-auto border border-border bg-surface">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup" class="sr-only md:not-sr-only md:table-header-group">
                    <tr role="row" class="border-b border-border bg-surface-muted">
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('vehicles.section.headers.plate') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('vehicles.section.headers.vehicle') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('vehicles.section.headers.year') }}</th>                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($customer->vehicles as $vehicle)
                        <x-vehicles.list-row :vehicle="$vehicle" />
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
