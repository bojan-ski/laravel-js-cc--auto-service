<x-layouts.app :title="__('parts.import.title')">
    <h1 class="text-2xl font-bold">{{ __('parts.import.title') }}</h1>

    <x-parts.import-errors :errors="$errors->import" />

    <x-parts.import-form :max-rows="$maxRows" />

    <x-parts.import-columns />
</x-layouts.app>
