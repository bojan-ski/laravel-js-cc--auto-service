@props(['items', 'showDiscount' => false])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-4">
    <h2 class="text-sm font-semibold uppercase tracking-wider">{{ __('invoices.show.line_items') }}</h2>

    <div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
        <table role="table" class="w-full text-left">
            <thead role="rowgroup" class="sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:table-header-group">
                <tr role="row">
                    <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('invoices.items.description') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('invoices.items.quantity') }}</th>
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('invoices.items.price') }}</th>
                    @if ($showDiscount)
                        <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('invoices.items.discount') }}</th>
                    @endif
                    <th role="columnheader" scope="col" class="{{ $cell }} text-end">{{ __('invoices.items.line_total') }}</th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                @foreach ($items as $item)
                    <tr role="row" class="border-b border-border last:border-b-0">
                        <td role="cell" class="px-3 py-2">{{ $item->description }}</td>

                        <td role="cell" class="px-3 py-2 text-end">
                            <x-ui.quantity :value="$item->quantity" />
                        </td>

                        <td role="cell" class="px-3 py-2 text-end">
                            <x-ui.money :value="$item->unit_price" />
                        </td>

                        @if ($showDiscount)
                            <td role="cell" class="px-3 py-2 text-end tabular-nums">
                                <x-ui.value :value="$item->hasDiscount() ? __('invoices.items.discount_value', ['percent' => $item->discountPercentLabel()]) : null" />
                            </td>
                        @endif

                        <td role="cell" class="px-3 py-2 text-end">
                            <x-ui.money :value="$item->lineTotal()" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
