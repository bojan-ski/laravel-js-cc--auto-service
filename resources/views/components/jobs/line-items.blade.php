@props(['job', 'showPricing', 'subtotal', 'discountTotal' => null, 'editing', 'discounting' => null, 'userOptions', 'defaultRate', 'adding', 'showPartForm', 'partOptions'])

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
            :discount-total="$discountTotal"
            :editing="$editing"
            :discounting="$discounting"
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
    @elseif ($discounting)
        <x-jobs.line-discount-form :job="$job" :item="$discounting" />
    @elseif ($showPartForm)
        @if ($partOptions === [])
            <div id="line-item-form" class="mt-4 border border-border bg-surface-muted p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">
                        {{ __('jobs.items.add.part_title') }}
                    </h3>

                    <x-jobs.add-switch :job="$job" :adding="$adding" />
                </div>

                <p class="mt-2 text-sm text-text-soft">{{ __('jobs.items.add.no_parts') }}</p>
            </div>
        @else
            <x-jobs.part-line-form
                :job="$job"
                :part-options="$partOptions"
                :show-pricing="$showPricing"
                :user-options="$userOptions"
            >
                <x-slot:switch>
                    <x-jobs.add-switch :job="$job" :adding="$adding" />
                </x-slot:switch>
            </x-jobs.part-line-form>
        @endif
    @else
        @can('createItem', $job)
            <x-jobs.line-item-form
                :job="$job"
                :show-pricing="$showPricing"
                :user-options="$userOptions"
                :default-rate="$defaultRate"
            >
                <x-slot:switch>
                    <x-jobs.add-switch :job="$job" :adding="$adding" />
                </x-slot:switch>
            </x-jobs.line-item-form>
        @endcan
    @endif
</section>
