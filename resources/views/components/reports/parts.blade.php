@props(['parts', 'withMargin' => false])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section {{ $attributes->class(['border border-border bg-surface']) }}>
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('reports.parts.title') }}</h2>

    @if ($parts['rows'] === [])
        <p class="px-3 py-2 text-sm text-text-muted">{{ __('reports.parts.empty') }}</p>
    @else
        <div class="overflow-x-auto md:overflow-x-visible">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:table-header-group">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('reports.parts.sku') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('reports.parts.name') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.parts.quantity') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.parts.revenue') }}</th>
                        @if ($withMargin)
                            <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.parts.cost') }}</th>
                            <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.parts.margin') }}</th>
                        @endif
                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($parts['rows'] as $row)
                        <tr role="row" class="border-b border-border">
                            <td role="cell" class="px-3 py-2 tabular-nums">{{ $row['part']->sku }}</td>
                            <td role="cell" class="px-3 py-2">
                                {{ $row['part']->name }}
                                @if ($row['part']->trashed())
                                    <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
                                @endif
                            </td>
                            <td role="cell" class="px-3 py-2 text-end tabular-nums">{{ $row['quantity'] }}</td>
                            <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$row['revenue']" /></td>
                            @if ($withMargin)
                                <td role="cell" class="px-3 py-2 text-end tabular-nums">
                                    @if ($row['cost'] === null)
                                        <x-ui.value :value="null" />
                                    @else
                                        <x-ui.money :value="$row['cost']" />
                                    @endif
                                </td>
                                <td role="cell" class="px-3 py-2 text-end tabular-nums">
                                    @if ($row['margin'] === null)
                                        <x-ui.value :value="null" />
                                    @else
                                        <x-ui.money :value="$row['margin']" />
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>

                <tfoot role="rowgroup" class="bg-surface-muted font-semibold">
                    <tr role="row">
                        <th role="rowheader" scope="row" colspan="2" class="px-3 py-2 text-start">{{ __('reports.parts.total') }}</th>
                        <td role="cell" class="px-3 py-2 text-end tabular-nums">{{ $parts['quantity'] }}</td>
                        <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$parts['revenue']" /></td>
                        @if ($withMargin)
                            <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$parts['cost']" /></td>
                            <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$parts['margin']" /></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($parts['without_cost'] > 0)
            <p class="border-t border-border px-3 py-2 text-sm text-text-muted">{{ trans_choice('reports.parts.without_cost', $parts['without_cost'], ['count' => $parts['without_cost']]) }}</p>
        @endif
    @endif
</section>
