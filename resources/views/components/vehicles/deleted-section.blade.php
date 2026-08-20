@props(['customer'])

<section class="mt-6">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('vehicles.deleted.title') }}</h2>

    <p class="mt-1 text-sm text-text-muted">{{ __('vehicles.deleted.intro') }}</p>

    <div class="mt-2 overflow-x-auto border border-dashed border-border-strong bg-surface-muted">
        <table role="table" class="w-full text-left">
            <thead role="rowgroup" class="sr-only">
                <tr role="row">
                    <th role="columnheader" scope="col">{{ __('vehicles.section.headers.plate') }}</th>
                    <th role="columnheader" scope="col">{{ __('vehicles.section.headers.vehicle') }}</th>
                    <th role="columnheader" scope="col">{{ __('vehicles.section.headers.actions') }}</th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                @foreach ($customer->deletedVehicles as $vehicle)
                    <x-vehicles.deleted-row :vehicle="$vehicle" />
                @endforeach
            </tbody>
        </table>
    </div>
</section>
