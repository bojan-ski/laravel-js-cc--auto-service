@props(['withTrashed'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:sticky md:top-0 md:table-header-group">
    <tr role="row">
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.headers.sku') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.headers.name') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.headers.stock') }}</th>

        @can('viewPricing', App\Models\Part::class)
            <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('parts.headers.price') }}</th>
        @endcan

        @can('viewCostPrice', App\Models\Part::class)
            <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('parts.headers.cost') }}</th>
        @endcan

        <th role="columnheader" scope="col" class="{{ $cell }} hidden lg:table-cell">{{ __('parts.headers.shelf') }}</th>

        @if ($withTrashed)
            <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('parts.headers.actions') }}</th>
        @endif
    </tr>
</thead>
