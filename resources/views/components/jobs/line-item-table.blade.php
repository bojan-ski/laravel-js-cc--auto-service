@props(['job', 'showPricing', 'subtotal', 'editing'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
    $labelColumns = 3;
@endphp

<div class="mt-2 overflow-x-auto border border-border">
    <table role="table" class="w-full text-left">
        <thead role="rowgroup">
            <tr role="row" class="border-b border-border bg-surface-muted">
                <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">
                    {{ __('jobs.items.headers.type') }}
                </th>

                <th role="columnheader" scope="col" class="{{ $cell }}">
                    {{ __('jobs.items.headers.description') }}
                </th>

                <th role="columnheader" scope="col" class="{{ $cell }} text-end">
                    {{ __('jobs.items.headers.quantity') }}
                </th>

                @if ($showPricing)
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">
                        {{ __('jobs.items.headers.price') }}
                    </th>
                @endif

                <th role="columnheader" scope="col" class="{{ $cell }}">
                    {{ __('jobs.items.headers.who') }}
                </th>

                <th role="columnheader" scope="col" class="{{ $cell }}">
                    <span class="sr-only">{{ __('jobs.items.headers.actions') }}</span>
                </th>
            </tr>
        </thead>

        <tbody role="rowgroup">
            @foreach ($job->items as $item)
                <x-jobs.line-item-row
                    :job="$job"
                    :item="$item"
                    :show-pricing="$showPricing"
                    :editing="$editing?->is($item)"
                />
            @endforeach
        </tbody>

        @if ($showPricing)
            <tfoot role="rowgroup">
                <tr role="row" class="border-t border-border-strong">
                    <th role="rowheader" scope="row" colspan="{{ $labelColumns }}" class="{{ $cell }} text-end">
                        {{ __('jobs.items.subtotal') }}
                    </th>

                    <td role="cell" class="px-3 py-2 text-end font-medium">
                        <x-ui.money :value="$subtotal" />
                    </td>

                    <td role="cell" colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
