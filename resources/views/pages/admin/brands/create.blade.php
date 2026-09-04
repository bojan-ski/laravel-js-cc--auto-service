<x-layouts.app :title="__('brands.create.title')">
    <h1 class="text-2xl font-bold">{{ __('brands.create.title') }}</h1>

    <x-brands.form
        :tiers="$tiers"
        :action="route('admin.brands.store')"
        :submit="__('brands.create.submit')"
        :cancel="route('admin.brands.index')"
    />
</x-layouts.app>
