@props(['part', 'withTrashed'])

<tr role="row" @class([
    'grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0',
    'bg-surface-muted text-text-muted' => $part->trashed(),
])>
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        <a href="{{ route('parts.show', $part) }}" class="block hover:text-brand-dark">
            <span class="block font-medium tabular-nums underline underline-offset-2">{{ $part->sku }}</span>
            <span class="sr-only">{{ __('parts.view_part', ['sku' => $part->sku]) }}</span>
        </a>
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm md:table-cell md:px-3 md:py-2 md:text-base">
        {{ $part->name }}

        @if ($part->trashed())
            <span class="ml-1 text-xs font-semibold uppercase tracking-wider">{{ __('parts.trashed.badge') }}</span>
        @endif
    </td>

    <td role="cell" @class(['hidden lg:table-cell lg:px-3 lg:py-2', 'text-text-muted' => $part->brand === null])>
        <x-ui.value :value="$part->brand?->name" />
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2 md:text-start">
        <x-parts.stock :part="$part" />
    </td>

    @can('viewPricing', App\Models\Part::class)
        <td role="cell" class="hidden md:table-cell md:px-3 md:py-2">
            <x-ui.money :value="$part->unit_price" />
        </td>
    @endcan

    @can('viewCostPrice', App\Models\Part::class)
        <td role="cell" class="hidden md:table-cell md:px-3 md:py-2">
            @if ($part->cost_price === null)
                <x-ui.value :value="null" />
            @else
                <x-ui.money :value="$part->cost_price" />
            @endif
        </td>
    @endcan

    <td role="cell" @class(['hidden lg:table-cell lg:px-3 lg:py-2', 'text-text-muted' => $part->location === null])>
        <x-ui.value :value="$part->location" />
    </td>

    @if ($withTrashed)
        <td role="cell" class="hidden md:table-cell md:px-3 md:py-2">
            @if ($part->trashed())
                @can('restore', $part)
                    <x-ui.action-form
                        :action="route('parts.restore', $part)"
                        :confirm="__('parts.restore.confirm', ['sku' => $part->sku])"
                        :aria-label="__('parts.restore.action', ['sku' => $part->sku])"
                    >
                        {{ __('parts.restore.label') }}
                    </x-ui.action-form>
                @endcan
            @endif
        </td>
    @endif
</tr>
