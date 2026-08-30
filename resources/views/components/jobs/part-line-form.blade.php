<form id="line-item-form" method="POST" action="{{ route('jobs.parts.store', $job) }}" class="mt-4 border-t border-border pt-4">
    @csrf

    <h3 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ __('jobs.items.add.part_title') }}</h3>

    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <x-forms.select
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

        @if ($userOptions !== [])
            <x-forms.select
                name="user_id"
                :label="__('jobs.items.add.who')"
                :options="$userOptions"
                :placeholder="__('jobs.items.add.who_unassigned')"
            />
        @endif

        @if ($showPricing)
            <x-forms.field
                name="unit_price"
                type="number"
                step="0.01"
                min="0"
                :label="__('jobs.items.add.price')"
            />
        @endif
    </div>

    <div class="mt-3">
        <x-ui.button>{{ __('jobs.items.add.submit_part') }}</x-ui.button>
    </div>
</form>
