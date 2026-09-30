@props(['history', 'current'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-6 print:hidden">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('estimates.show.history') }}</h2>

    <div class="mt-2 overflow-x-auto border border-border bg-surface">
        <table role="table" class="w-full text-left">
            <thead role="rowgroup" class="sr-only md:not-sr-only md:table-header-group">
                <tr role="row" class="border-b border-border bg-surface-muted">
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('estimates.history_headers.number') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('estimates.history_headers.status') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('estimates.history_headers.created') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('estimates.history_headers.total') }}</th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                @foreach ($history as $estimate)
                    <x-estimates.history-row :estimate="$estimate" :current="$estimate->is($current)" />
                @endforeach
            </tbody>
        </table>
    </div>
</section>
