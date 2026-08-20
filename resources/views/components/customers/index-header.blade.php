<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('customers.title') }}</h1>

    @can('create', App\Models\Customer::class)
        <x-ui.button :href="route('customers.create')">{{ __('customers.index.new') }}</x-ui.button>
    @endcan
</div>
