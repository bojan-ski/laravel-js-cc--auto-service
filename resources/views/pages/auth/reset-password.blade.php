<x-layouts.guest :title="__('app.auth.reset.title')">
    <h1 class="text-lg font-semibold">{{ __('app.auth.reset.title') }}</h1>

    <form method="POST" action="{{ route('password.store') }}" class="mt-5 space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-forms.field
            name="email"
            type="email"
            :label="__('app.auth.reset.email')"
            :value="$email"
            readonly
            autocomplete="username"
        />

        <x-forms.field
            name="password"
            type="password"
            :label="__('app.auth.reset.password')"
            required
            autofocus
            autocomplete="new-password"
        />

        <x-forms.field
            name="password_confirmation"
            type="password"
            :label="__('app.auth.reset.confirm')"
            required
            autocomplete="new-password"
        />

        <x-forms.submit>{{ __('app.auth.reset.submit') }}</x-forms.submit>
    </form>
</x-layouts.guest>
