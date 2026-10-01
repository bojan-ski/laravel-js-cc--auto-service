@props(['rows'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="border border-border bg-surface">
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('reports.vat.title') }}</h2>

    <div class="overflow-x-auto md:overflow-x-visible">
        <table role="table" class="w-full text-left">
            <thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:table-header-group">
                <tr role="row">
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('reports.vat.rate') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.vat.base') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.vat.vat') }}</th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                @foreach ($rows as $row)
                    <tr role="row" class="border-b border-border last:border-b-0">
                        <td role="cell" class="px-3 py-2 tabular-nums">{{ __('reports.vat.rate_value', ['rate' => $row['rate']]) }}</td>
                        <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$row['base']" /></td>
                        <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$row['vat']" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
