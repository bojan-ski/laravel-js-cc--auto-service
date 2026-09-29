@props(['job', 'item'])

@php
    $hintId = $errors->has('discount_percent') ? null : 'discount_percent-hint';
@endphp

<form id="line-discount-form" method="POST" action="{{ route('job-items.discount.update', $item) }}" class="mt-4 border border-border bg-surface-muted p-3">
    @csrf
    @method('PATCH')

    <h3 class="text-xs font-semibold uppercase tracking-wider text-text-soft">
        {{ __('jobs.items.discount.title_for', ['description' => $item->description]) }}
    </h3>

    <div class="mt-2 grid gap-3 sm:grid-cols-[minmax(0,28rem)_auto] sm:items-start">
        <div>
            <x-forms.field
                name="discount_percent"
                type="number"
                step="0.01"
                min="0"
                max="100"
                inputmode="decimal"
                :label="__('jobs.items.discount.percent')"
                :value="$item->discount_percent"
                :aria-describedby="$hintId"
                class="tabular-nums sm:max-w-40"
                required
            />

            <p id="discount_percent-hint" class="mt-1 text-sm text-text-muted">{{ __('jobs.items.discount.hint') }}</p>
        </div>

        <div class="flex flex-wrap gap-2 sm:pt-6">
            <x-ui.button class="sm:h-10.5">{{ __('jobs.items.discount.save') }}</x-ui.button>

            <x-ui.button variant="secondary" class="sm:h-10.5" :href="route('jobs.show', $job)">
                {{ __('jobs.items.discount.cancel') }}
            </x-ui.button>
        </div>
    </div>
</form>
