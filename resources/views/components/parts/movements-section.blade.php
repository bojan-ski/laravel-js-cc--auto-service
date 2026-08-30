@props(['part', 'movements', 'total'])

@php
    $cell = 'px-3 py-2 text-xs font-medium uppercase tracking-wider';
@endphp

<section class="mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('parts.show.movements') }}</h2>

        @if (! $part->trashed())
            @can('adjustStock', App\Models\Part::class)
                <x-ui.button
                    variant="secondary"
                    :href="route('parts.adjust.create', $part)"
                    :aria-label="__('parts.adjust.action_for', ['sku' => $part->sku])"
                >
                    {{ __('parts.adjust.action') }}
                </x-ui.button>
            @endcan
        @endif
    </div>

    @if ($movements->isEmpty())
        <x-ui.empty-state class="mt-2" :level="0" :title="__('parts.show.movements_empty')" />
    @else
        <div class="mt-2 overflow-x-auto border border-border bg-surface">
            <table role="table" class="w-full text-left">
                <thead role="rowgroup" class="sr-only md:not-sr-only md:table-header-group">
                    <tr role="row" class="border-b border-border bg-surface-muted">
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.movements.headers.when') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.movements.headers.change') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }}">{{ __('parts.movements.headers.reason') }}</th>
                        <th role="columnheader" scope="col" class="{{ $cell }} hidden md:table-cell">{{ __('parts.movements.headers.who') }}</th>
                    </tr>
                </thead>

                <tbody role="rowgroup">
                    @foreach ($movements as $movement)
                        <x-parts.movement-row :movement="$movement" />
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($total > $movements->count())
            <p class="mt-2 text-end text-sm text-text-muted">
                {{ __('parts.movements.showing', ['shown' => $movements->count(), 'total' => $total]) }}
            </p>
        @endif
    @endif
</section>
