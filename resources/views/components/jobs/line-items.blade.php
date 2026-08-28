@props(['job', 'showPricing', 'subtotal', 'editing', 'userOptions', 'defaultRate'])

<section class="border border-border bg-surface p-4">
    <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">
        {{ __('jobs.show.line_items') }}
    </h2>

    @if ($job->items->isEmpty())
        <x-ui.empty-state class="mt-2" :title="__('jobs.show.line_items_empty')" :level="0" />
    @else
        <x-jobs.line-item-table
            :job="$job"
            :show-pricing="$showPricing"
            :subtotal="$subtotal"
            :editing="$editing"
        />
    @endif

    @if ($job->needsLineItemsToComplete())
        <p class="mt-2 text-sm text-text-soft">{{ __('jobs.items.complete_hint') }}</p>
    @endif

    @if ($editing)
        <x-jobs.line-item-form
            :job="$job"
            :item="$editing"
            :show-pricing="$showPricing"
            :user-options="$userOptions"
            :default-rate="$defaultRate"
        />
    @else
        @can('createItem', $job)
            <x-jobs.line-item-form
                :job="$job"
                :show-pricing="$showPricing"
                :user-options="$userOptions"
                :default-rate="$defaultRate"
            />
        @endcan
    @endif
</section>
