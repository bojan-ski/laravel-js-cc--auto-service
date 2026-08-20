@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="hidden md:table-header-group">
    <tr role="row" class="border-b border-border bg-surface-muted">
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('customers.index.headers.name') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('customers.index.headers.phone') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} hidden xl:table-cell">{{ __('customers.index.headers.email') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('customers.index.headers.actions') }}</th>
    </tr>
</thead>
