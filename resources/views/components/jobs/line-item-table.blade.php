@props(['job', 'showPricing', 'subtotal', 'discountTotal' => null, 'editing', 'discounting' => null])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<div class="relative mt-2 overflow-x-auto border border-border">
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

                    <th role="columnheader" scope="col" class="{{ $cell }} hidden text-end md:table-cell">
                        {{ __('jobs.items.headers.discount') }}
                    </th>

                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">
                        {{ __('jobs.items.headers.line_total') }}
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
                    :discounting="$discounting?->is($item)"
                />
            @endforeach
        </tbody>

        @if ($showPricing)
            <tfoot role="rowgroup" class="border-t border-border-strong">
                @if ($discountTotal !== null)
                    <x-jobs.line-item-total-row :label="__('jobs.items.discount_total')" :amount="$discountTotal" />
                @endif

                <x-jobs.line-item-total-row :label="__('jobs.items.subtotal')" :amount="$subtotal" />
            </tfoot>
        @endif
    </table>
</div>
