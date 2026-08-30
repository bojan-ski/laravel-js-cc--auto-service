@props(['job', 'showPricing', 'subtotal', 'editing', 'userOptions', 'defaultRate', 'adding', 'showPartForm', 'partOptions'])

<section class="border border-border bg-surface p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-text-muted">
            {{ __('jobs.show.line_items') }}
        </h2>

        @if ($editing === null)
            @can('createItem', $job)
                <div role="group" aria-label="{{ __('jobs.items.add.group_label') }}" class="flex flex-wrap gap-2">
                    <x-jobs.add-link
                        :href="route('jobs.show', ['job' => $job, 'add' => 'labor']).'#line-item-form'"
                        :active="$adding === 'labor'"
                        :aria-label="__('jobs.items.add.labor_link_label')"
                    >
                        {{ __('jobs.items.add.labor_link') }}
                    </x-jobs.add-link>

                    <x-jobs.add-link
                        :href="route('jobs.show', ['job' => $job, 'add' => 'part']).'#line-item-form'"
                        :active="$adding === 'part'"
                        :aria-label="__('jobs.items.add.part_link_label')"
                    >
                        {{ __('jobs.items.add.part_link') }}
                    </x-jobs.add-link>
                </div>
            @endcan
        @endif
    </div>

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
    @elseif ($showPartForm)
        @if ($partOptions === [])
            <p class="mt-4 border-t border-border pt-4 text-sm text-text-soft">
                {{ __('jobs.items.add.no_parts') }}
            </p>
        @else
            <x-jobs.part-line-form
                :job="$job"
                :part-options="$partOptions"
                :show-pricing="$showPricing"
                :user-options="$userOptions"
            />
        @endif
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
