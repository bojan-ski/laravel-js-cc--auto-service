<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('parts.title') }}</h1>

    <div class="flex flex-wrap items-center gap-2">
        @can('import', App\Models\Part::class)
            <x-ui.button variant="secondary" :href="route('parts.import.create')">
                {{ __('parts.import.action') }}
            </x-ui.button>
        @endcan

        @can('create', App\Models\Part::class)
            <x-ui.button :href="route('parts.create')">{{ __('parts.new') }}</x-ui.button>
        @endcan
    </div>
</div>
