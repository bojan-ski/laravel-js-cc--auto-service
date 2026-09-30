@props(['showActions'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:sticky md:top-0 md:table-header-group">
    <tr role="row">
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('templates.headers.name') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('templates.headers.description') }}</th>
        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('templates.headers.lines') }}</th>

        @if ($showActions)
            <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('templates.index.headers.actions') }}</th>
        @endif
    </tr>
</thead>
