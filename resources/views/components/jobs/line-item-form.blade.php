<form id="line-item-form" method="POST" action="{{ $action() }}" class="mt-4 border border-border bg-surface-muted p-3">
    @csrf

    @if ($isEdit())
        @method('PUT')
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">{{ $heading() }}</h3>

        {{ $switch ?? '' }}
    </div>

    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_7rem_10rem_14rem_auto]">
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

        @if ($userOptions !== [])
            <x-forms.select
                class="lg:h-10.5"
                name="user_id"
                :label="__('jobs.items.add.who')"
                :options="$userOptions"
                :selected="$item?->user_id"
                :placeholder="$assigneePlaceholder()"
                :required="! $isPart()"
            />
        @endif

        <div class="flex flex-wrap gap-2 lg:pt-6">
            <x-ui.button class="lg:h-10.5">{{ $submitLabel() }}</x-ui.button>

            @if ($isEdit())
                <x-ui.button variant="secondary" class="lg:h-10.5" :href="route('jobs.show', $job)">
                    {{ __('jobs.items.edit.cancel') }}
                </x-ui.button>
            @endif
        </div>
    </div>
</form>
