<form id="line-item-form" method="POST" action="{{ $action() }}" class="mt-4 border-t border-border pt-4">
    @csrf

    @if ($isEdit())
        @method('PUT')
    @endif

    <h3 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $heading() }}</h3>

    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <x-forms.field
            name="description"
            :label="__('jobs.items.add.description')"
            :value="$item?->description"
            :placeholder="$descriptionPlaceholder()"
            :required="! $isPart()"
        />

        <x-forms.field
            name="quantity"
            type="number"
            :step="$quantityStep()"
            :min="$quantityMin()"
            :label="$quantityLabel()"
            :value="$quantityValue()"
            required
        />

        @if ($userOptions !== [])
            <x-forms.select
                name="user_id"
                :label="__('jobs.items.add.who')"
                :options="$userOptions"
                :selected="$item?->user_id"
                :placeholder="$assigneePlaceholder()"
                :required="! $isPart()"
            />
        @endif

        @if ($showPricing)
            <x-forms.field
                name="unit_price"
                type="number"
                step="0.01"
                min="0"
                :label="$rateLabel()"
                :value="$item?->unit_price ?? $defaultRate"
            />
        @endif
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <x-ui.button>{{ $submitLabel() }}</x-ui.button>

        @if ($isEdit())
            <x-ui.button variant="secondary" :href="route('jobs.show', $job)">
                {{ __('jobs.items.edit.cancel') }}
            </x-ui.button>
        @endif
    </div>
</form>
