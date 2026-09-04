<x-layouts.app :title="__('brands.edit.title', ['name' => $brand->name])">
    <h1 class="text-2xl font-bold">{{ __('brands.edit.title', ['name' => $brand->name]) }}</h1>

    <x-brands.form
        :brand="$brand"
        :tiers="$tiers"
        method="PUT"
        :action="route('admin.brands.update', $brand)"
        :submit="__('brands.edit.submit')"
        :cancel="route('admin.brands.index')"
    />
</x-layouts.app>
