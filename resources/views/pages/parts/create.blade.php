<x-layouts.app :title="__('parts.create.title')">
    <h1 class="text-2xl font-bold">{{ __('parts.create.title') }}</h1>

    <x-parts.form
        :action="route('parts.store')"
        :submit="__('parts.create.submit')"
        :cancel="route('parts.index')"
    />
</x-layouts.app>
