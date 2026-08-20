@props(['code', 'title', 'message'])

<x-layouts.guest :title="$title" width="max-w-md">
    <div class="text-center">
        <h1 class="text-5xl font-bold tabular-nums">{{ $code }}</h1>

        <p class="mt-3 text-base font-semibold">{{ $title }}</p>

        <p class="mt-2 text-sm text-text-soft">{{ $message }}</p>
    </div>

    @auth
        <x-forms.footer-link :href="route('dashboard')">
            {{ __('errors.back_to_dashboard') }}
        </x-forms.footer-link>
    @else
        <x-forms.footer-link :href="route('login')">
            {{ __('errors.back_to_login') }}
        </x-forms.footer-link>
    @endauth
</x-layouts.guest>
