@props(['vehicle', 'jobs', 'total'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-6">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('vehicles.show.history') }}</h2>

    @if ($jobs->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('vehicles.show.history_empty')" :level="0" />
    @else
        <div class="mt-2 overflow-x-auto border border-border bg-surface">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup" class="hidden md:table-header-group">
                    <tr role="row" class="border-b border-border bg-surface-muted">
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('jobs.history.headers.reference') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('jobs.history.headers.customer') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('jobs.history.headers.status') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('jobs.history.headers.received') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('jobs.history.headers.mileage') }}</th>
                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($jobs as $job)
                        <x-vehicles.service-history-row :job="$job" />
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($total > $jobs->count())
            <p class="mt-2 text-end text-sm">
                <a
                    href="{{ route('jobs.index', ['search' => $vehicle->registration_plate]) }}"
                    class="font-medium underline underline-offset-2 hover:text-brand-dark"
                    aria-label="{{ __('vehicles.show.history_view_all_for', ['count' => $total, 'plate' => $vehicle->registration_plate]) }}"
                >
                    {{ __('jobs.history.view_all', ['count' => $total]) }}
                </a>
            </p>
        @endif
    @endif
</section>
