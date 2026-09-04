<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('brands.title') }}</h1>

    @can('create', App\Models\Brand::class)
        <x-ui.button :href="route('admin.brands.create')">{{ __('brands.new') }}</x-ui.button>
    @endcan
</div>
