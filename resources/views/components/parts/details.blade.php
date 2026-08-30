@props(['part'])

@php
    $term = 'text-xs font-medium uppercase tracking-wider text-text-muted sm:pt-0.5';
@endphp

<section class="mt-4 border border-border bg-surface p-4">
    <h2 class="sr-only">{{ __('parts.show.details') }}</h2>

    <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
        <dt class="{{ $term }}">{{ __('parts.show.stock') }}</dt>
        <dd><x-parts.stock :part="$part" /></dd>

        <dt class="{{ $term }}">{{ __('parts.show.reorder_level') }}</dt>
        <dd class="tabular-nums">{{ $part->reorder_level }}</dd>

        @can('viewPricing', App\Models\Part::class)
            <dt class="{{ $term }}">{{ __('parts.show.price') }}</dt>
            <dd><x-ui.money :value="$part->unit_price" /></dd>
        @endcan

        @can('viewCostPrice', App\Models\Part::class)
            <dt class="{{ $term }}">{{ __('parts.show.cost') }}</dt>
            <dd @class(['text-text-muted' => $part->cost_price === null])>
                @if ($part->cost_price === null)
                    <x-ui.value :value="null" />
                @else
                    <x-ui.money :value="$part->cost_price" />
                @endif
            </dd>
        @endcan

        <dt class="{{ $term }}">{{ __('parts.show.shelf') }}</dt>
        <dd @class(['text-text-muted' => $part->location === null])>
            <x-ui.value :value="$part->location" />
        </dd>

        <dt class="{{ $term }}">{{ __('parts.show.description') }}</dt>
        <dd @class(['whitespace-pre-line', 'text-text-muted' => $part->description === null])>
            <x-ui.value :value="$part->description" />
        </dd>
    </dl>
</section>
