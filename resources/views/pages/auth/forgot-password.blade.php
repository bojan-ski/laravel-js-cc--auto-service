<x-layouts.guest :title="__('app.auth.forgot.title')">
    <h1 class="text-lg font-semibold">{{ __('app.auth.forgot.title') }}</h1>

    <p class="mt-2 text-sm text-text-muted">{{ __('app.auth.forgot.intro') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
        @csrf

        <x-forms.field
            name="email"
            type="email"
            :label="__('app.auth.forgot.email')"
            required
            autofocus
            autocomplete="username"
        />

        <x-forms.submit>{{ __('app.auth.forgot.submit') }}</x-forms.submit>
    </form>

    <x-forms.footer-link :href="route('login')">
        {{ __('app.auth.forgot.back') }}
    </x-forms.footer-link>
</x-layouts.guest>
