@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="text-lg font-semibold">{{ __('parts.import.columns_heading') }}</h2>

    <div class="mt-2 overflow-x-auto md:overflow-x-visible">
        <table role="table" class="w-full text-left">
            <thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:table-header-group">
                <tr role="row">
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.import.column_header') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.import.column_required') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.import.column_meaning') }}</th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                @foreach ($rows as $row)
                    <tr role="row" class="grid grid-cols-[1fr_auto] items-start gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0">
                        <td role="cell" class="col-start-1 row-start-1 font-medium tabular-nums md:table-cell md:px-3 md:py-2">
                            {{ $row['header'] }}
                        </td>

                        <td role="cell" class="col-start-2 row-start-1 text-end text-sm text-text-muted md:table-cell md:px-3 md:py-2 md:text-start md:text-base md:text-text">
                            {{ $row['requirement'] }}
                        </td>

                        <td role="cell" class="col-span-2 col-start-1 row-start-2 text-sm text-text-soft md:table-cell md:px-3 md:py-2 md:text-base">
                            {{ $row['description'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
