@props(['job', 'item', 'showPricing', 'editing' => false, 'discounting' => false])

@php
    $context = ['description' => $item->description];
    $control = 'px-2 py-1 text-xs';
    $marked = $editing || $discounting;
@endphp

<tr
    role="row"
    @if ($marked) aria-current="true" @endif
    @class(['border-b border-border last:border-b-0', 'bg-surface-muted' => $marked])
>
    <td role="cell" class="hidden px-3 py-2 md:table-cell">{{ $item->type->label() }}</td>

    <td role="cell" class="px-3 py-2">
        {{ $item->description }}

        @if ($editing)
            <span class="sr-only">{{ __('jobs.items.edit.row_marker') }}</span>
        @elseif ($discounting)
            <span class="sr-only">{{ __('jobs.items.discount.row_marker') }}</span>
        @endif
    </td>

    <td role="cell" class="px-3 py-2 text-end">
        <x-ui.quantity :value="$item->quantity" />
    </td>

    @if ($showPricing)
        <td role="cell" class="px-3 py-2 text-end">
            <x-ui.money :value="$item->unit_price" />
        </td>

        <td role="cell" class="hidden px-3 py-2 text-end tabular-nums md:table-cell">
            <x-ui.value :value="$item->hasDiscount() ? __('jobs.items.discount_value', ['percent' => $item->discountPercentLabel()]) : null" />
        </td>

        <td role="cell" class="px-3 py-2 text-end">
            <x-ui.money :value="$item->lineTotal()" />
        </td>
    @endif

    <td role="cell" class="px-3 py-2">
        <x-ui.value :value="$item->user?->shortName()" />

        @if ($item->user?->trashed())
            <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
        @endif
    </td>

    <td role="cell" class="px-3 py-2">
        <div class="flex justify-end gap-2">
            @can('update', $item)
                <x-ui.button
                    variant="secondary"
                    :href="route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]).'#line-item-form'"
                    :aria-label="__('jobs.items.edit.open_line', $context)"
                    :class="$control"
                >
                    {{ __('jobs.items.edit.open') }}
                </x-ui.button>
            @endcan

            @can('discount', $item)
                <x-ui.button
                    variant="secondary"
                    :href="route('jobs.show', ['job' => $job, 'discount' => $item->getKey()]).'#line-discount-form'"
                    :aria-label="__('jobs.items.discount.open_line', $context)"
                    :class="$control"
                >
                    {{ __('jobs.items.discount.open') }}
                </x-ui.button>
            @endcan

            @can('delete', $item)
                <x-ui.action-form
                    method="DELETE"
                    variant="danger"
                    :action="route('job-items.destroy', $item)"
                    :confirm="__('jobs.items.delete.confirm', $context)"
                    :aria-label="__('jobs.items.delete.label_line', $context)"
                    :class="$control"
                >
                    {{ __('jobs.items.delete.label') }}
                </x-ui.action-form>
            @endcan
        </div>
    </td>
</tr>
