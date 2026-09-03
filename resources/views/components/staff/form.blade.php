@php
    $isCreate = $member === null;
    $passwordRequired = $isCreate ? true : null;
    $passwordNote = ! $isCreate && ! $errors->has('password') ? 'password-note' : null;
@endphp

<form method="POST" action="{{ $action }}" class="mt-4 max-w-2xl space-y-4">
    @csrf

    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-forms.field
        name="name"
        :label="__('staff.form.name')"
        :value="$member?->name"
        autocomplete="name"
        required
        autofocus
    />

    <x-forms.field
        name="email"
        type="email"
        :label="__('staff.form.email')"
        :value="$member?->email"
        autocomplete="email"
        required
    />

    @if ($lockedRole)
        <dl>
            <dt class="text-sm font-medium">{{ __('staff.form.role') }}</dt>

            <dd class="mt-1">
                <x-staff.role-badge :role="$member->role" />

                <span class="mt-1 block text-sm text-text-soft">{{ __('staff.form.own_role_note') }}</span>
            </dd>
        </dl>
    @else
        <x-forms.select
            name="role"
            :label="__('staff.form.role')"
            :options="$roleOptions()"
            :selected="$member?->role?->value"
            :placeholder="__('staff.form.role_placeholder')"
        />
    @endif

    <x-forms.field
        name="password"
        type="password"
        :label="$isCreate ? __('staff.form.password') : __('staff.form.password_optional')"
        :required="$passwordRequired"
        :aria-describedby="$passwordNote"
        autocomplete="new-password"
    />

    @unless ($isCreate)
        <p id="password-note" class="text-sm text-text-soft">{{ __('staff.form.password_note') }}</p>
    @endunless

    <x-forms.field
        name="password_confirmation"
        type="password"
        :label="__('staff.form.password_confirmation')"
        :required="$passwordRequired"
        autocomplete="new-password"
    />

    <div class="flex flex-wrap gap-2">
        <x-ui.button type="submit">{{ $submit }}</x-ui.button>

        <x-ui.button variant="secondary" :href="$cancel">{{ __('app.actions.cancel') }}</x-ui.button>
    </div>
</form>
