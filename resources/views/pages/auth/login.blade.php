<x-layouts.guest :title="__('app.auth.login.title')">
    <h1 class="text-lg font-semibold">{{ __('app.auth.login.title') }}</h1>

    <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4">
        @csrf

        <x-forms.field
            name="email"
            type="email"
            :label="__('app.auth.login.email')"
            required
            autofocus
            autocomplete="username"
        />

        <x-forms.field
            name="password"
            type="password"
            :label="__('app.auth.login.password')"
            required
            autocomplete="current-password"
        />

        <x-forms.checkbox name="remember" :label="__('app.auth.login.remember')" />

        <x-forms.submit>{{ __('app.auth.login.submit') }}</x-forms.submit>
    </form>

    <x-forms.footer-link :href="route('password.request')">
        {{ __('app.auth.login.forgot_link') }}
    </x-forms.footer-link>
</x-layouts.guest>
