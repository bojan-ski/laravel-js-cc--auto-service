<form id="line-item-form" method="POST" action="{{ route('jobs.parts.store', $job) }}" class="mt-4 border border-border bg-surface-muted p-3">
    @csrf

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ __('jobs.items.add.part_title') }}</h3>

        {{ $switch ?? '' }}
    </div>

    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_7rem_10rem_14rem_auto]">
        <x-forms.select
            class="lg:h-10.5"
            name="part_id"
            :label="__('jobs.items.add.part')"
            :options="$partOptions"
            :placeholder="__('parts.picker.placeholder')"
            required
        />

        <x-forms.field
            name="quantity"
            type="number"
            :step="$quantityStep()"
            :min="$quantityMin()"
            :label="__('jobs.items.add.quantity_units')"
            value="1"
            required
        />

        @if ($showPricing)
            <x-forms.field
                name="unit_price"
                type="number"
                step="0.01"
                min="0"
                :label="__('jobs.items.add.price')"
                :placeholder="__('jobs.items.add.price_placeholder')"
            />
        @endif

        @if ($userOptions !== [])
            <x-forms.select
                class="lg:h-10.5"
                name="user_id"
                :label="__('jobs.items.add.who')"
                :options="$userOptions"
                :placeholder="__('jobs.items.add.who_unassigned')"
            />
        @endif

        <div class="lg:pt-6">
            <x-ui.button class="lg:h-10.5">{{ __('jobs.items.add.submit_part') }}</x-ui.button>
        </div>
    </div>
</form>
