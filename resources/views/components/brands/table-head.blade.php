@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:sticky md:top-0 md:table-header-group">
    <tr role="row">
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('brands.headers.name') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('brands.headers.tier') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} hidden lg:table-cell">{{ __('brands.headers.parts') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('brands.headers.actions') }}</th>
    </tr>
</thead>
