@props(['rows'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="border border-border bg-surface">
    <h2 class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('reports.labor.title') }}</h2>

    @if ($rows === [])
        <p class="px-3 py-2 text-sm text-text-muted">{{ __('reports.labor.empty') }}</p>
    @else
        <div class="overflow-x-auto md:overflow-x-visible">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:table-header-group">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('reports.labor.mechanic') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.labor.hours') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('reports.labor.amount') }}</th>
                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($rows as $row)
                        <tr role="row" class="border-b border-border last:border-b-0">
                            <td role="cell" class="px-3 py-2">
                                {{ $row['user']->name }}
                                @if ($row['user']->trashed())
                                    <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
                                @endif
                            </td>
                            <td role="cell" class="px-3 py-2 text-end"><x-ui.quantity :value="$row['hours']" /></td>
                            <td role="cell" class="px-3 py-2 text-end"><x-ui.money :value="$row['amount']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
