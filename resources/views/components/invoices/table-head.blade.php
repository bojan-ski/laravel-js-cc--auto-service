@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:sticky md:top-0 md:table-header-group">
    <tr role="row">
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('invoices.headers.number') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('invoices.headers.customer') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} hidden xl:table-cell">{{ __('invoices.headers.issued') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('invoices.headers.total') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('invoices.headers.status') }}</th>
    </tr>
</thead>
