@props(['job', 'item' => null, 'showPricing', 'userOptions', 'defaultRate'])

@php
    $isEdit = $item !== null;
    $action = $isEdit ? route('job-items.update', $item) : route('jobs.items.store', $job);
    $heading = $isEdit
        ? __('jobs.items.edit.title_for', ['description' => $item->description])
        : __('jobs.items.add.title');
    $submit = $isEdit ? __('jobs.items.edit.save') : __('jobs.items.add.submit');
@endphp

<form id="line-item-form" method="POST" action="{{ $action }}" class="mt-4 border-t border-border pt-4">
    @csrf

    @if ($isEdit)
        @method('PUT')
    @endif

    <h3 class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $heading }}</h3>

    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <x-forms.field
            name="description"
            :label="__('jobs.items.add.description')"
            :value="$item?->description"
            :placeholder="__('jobs.items.add.description_placeholder')"
            required
        />

        <x-forms.field
            name="quantity"
            type="number"
            step="0.01"
            min="0.01"
            :label="__('jobs.items.add.quantity')"
            :value="$item?->quantity"
            required
        />

        @if ($userOptions !== [])
            <x-forms.select
                name="user_id"
                :label="__('jobs.items.add.who')"
                :options="$userOptions"
                :selected="$item?->user_id"
                :placeholder="__('jobs.items.add.who_placeholder')"
                required
            />
        @endif

        @if ($showPricing)
            <x-forms.field
                name="unit_price"
                type="number"
                step="0.01"
                min="0"
                :label="__('jobs.items.add.rate')"
                :value="$item?->unit_price ?? $defaultRate"
            />
        @endif
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <x-ui.button>{{ $submit }}</x-ui.button>

        @if ($isEdit)
            <x-ui.button variant="secondary" :href="route('jobs.show', $job)">
                {{ __('jobs.items.edit.cancel') }}
            </x-ui.button>
        @endif
    </div>
</form>
