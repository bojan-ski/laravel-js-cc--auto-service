@props([
    'action',
    'method' => 'POST',
    'owner',
    'vehicle' => null,
    'submit',
    'cancel',
])

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="border border-border bg-surface-muted px-3 py-2">
        <span class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ __('vehicles.form.owner') }}</span>
        <span class="ms-2 font-medium">{{ $owner->name }}</span>
    </div>

    <x-forms.field
        name="registration_plate"
        :label="__('vehicles.form.registration_plate')"
        :value="$vehicle?->registration_plate"
        class="tabular-nums"
        required
        autofocus
    />

    <x-forms.field name="make" :label="__('vehicles.form.make')" :value="$vehicle?->make" required />

    <x-forms.field name="model" :label="__('vehicles.form.model')" :value="$vehicle?->model" required />

    <x-forms.field name="year" type="number" :label="__('vehicles.form.year')" :value="$vehicle?->year" class="tabular-nums" />

    <x-forms.field name="vin" :label="__('vehicles.form.vin')" :value="$vehicle?->vin" class="tabular-nums" />

    <x-forms.field name="engine" :label="__('vehicles.form.engine')" :value="$vehicle?->engine" />

    <x-forms.field name="mileage" type="number" :label="__('vehicles.form.mileage')" :value="$vehicle?->mileage" class="tabular-nums" />

    <x-forms.textarea name="notes" :label="__('vehicles.form.notes')" :value="$vehicle?->notes" />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
