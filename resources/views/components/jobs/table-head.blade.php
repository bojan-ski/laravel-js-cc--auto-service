@props(['filters'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="sticky top-0 hidden border-b border-border-strong bg-surface-muted md:table-header-group">
    <tr role="row">
        <x-jobs.sort-header column="plate" :label="__('jobs.board.headers.plate')" :filters="$filters" :class="$cell" />
        <th role="columnheader" scope="col" class="{{ $cell }} hidden xl:table-cell">{{ __('jobs.board.headers.vehicle') }}</th>
        <x-jobs.sort-header column="customer" :label="__('jobs.board.headers.customer')" :filters="$filters" :class="$cell" />
        <x-jobs.sort-header column="status" :label="__('jobs.board.headers.status')" :filters="$filters" :class="$cell" />
        <x-jobs.sort-header column="received" :label="__('jobs.board.headers.received')" :filters="$filters" :class="$cell" />
        <th role="columnheader" scope="col" class="{{ $cell }} hidden xl:table-cell">{{ __('jobs.board.headers.assigned') }}</th>
    </tr>
</thead>
