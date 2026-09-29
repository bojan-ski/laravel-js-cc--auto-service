@props([
    'action',
    'method' => 'POST',
    'customer' => null,
    'submit',
    'cancel',
])

@php
    $taxIdHintId = $errors->has('tax_id') ? null : 'company-hint';
    $registrationNumberHintId = $errors->has('registration_number') ? null : 'company-hint';
@endphp

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field name="name" :label="__('customers.form.name')" :value="$customer?->name" required autofocus />

    <x-forms.field name="phone" :label="__('customers.form.phone')" :value="$customer?->phone" required />

    <x-forms.field name="email" type="email" :label="__('customers.form.email')" :value="$customer?->email" />

    <x-forms.field name="address" :label="__('customers.form.address')" :value="$customer?->address" />

    <fieldset class="space-y-2">
        <legend class="text-sm font-semibold">{{ __('customers.form.company') }}</legend>

        <p id="company-hint" class="text-sm text-text-muted">{{ __('customers.form.company_hint') }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.field
                name="tax_id"
                :label="__('customers.form.tax_id')"
                :value="$customer?->tax_id"
                :aria-describedby="$taxIdHintId"
                inputmode="numeric"
                class="tabular-nums"
            />

            <x-forms.field
                name="registration_number"
                :label="__('customers.form.registration_number')"
                :value="$customer?->registration_number"
                :aria-describedby="$registrationNumberHintId"
                inputmode="numeric"
                class="tabular-nums"
            />
        </div>
    </fieldset>

    <x-forms.textarea name="notes" :label="__('customers.form.notes')" :value="$customer?->notes" />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
