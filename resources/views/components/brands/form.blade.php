@props([
    'brand' => null,
    'tiers',
    'action',
    'method' => 'POST',
    'submit',
    'cancel',
])

@php
    $tierNote = $errors->has('quality_tier') ? null : 'tier-note';
@endphp

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field
        name="name"
        :label="__('brands.form.name')"
        :value="$brand?->name"
        required
        autofocus
    />

    <x-forms.select
        name="quality_tier"
        :label="__('brands.form.quality_tier')"
        :options="$tiers"
        :selected="$brand?->quality_tier?->value"
        :aria-describedby="$tierNote"
    />

    <p id="tier-note" class="text-sm text-text-soft">{{ __('brands.form.tier_note') }}</p>

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
