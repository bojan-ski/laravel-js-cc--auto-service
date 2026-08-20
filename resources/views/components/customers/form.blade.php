@props([
    'action',
    'method' => 'POST',
    'customer' => null,
    'submit',
    'cancel',
])

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field name="name" :label="__('customers.form.name')" :value="$customer?->name" required autofocus />

    <x-forms.field name="phone" :label="__('customers.form.phone')" :value="$customer?->phone" required />

    <x-forms.field name="email" type="email" :label="__('customers.form.email')" :value="$customer?->email" />

    <x-forms.field name="address" :label="__('customers.form.address')" :value="$customer?->address" />

    <x-forms.textarea name="notes" :label="__('customers.form.notes')" :value="$customer?->notes" />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
