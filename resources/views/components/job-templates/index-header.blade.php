<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('templates.index.title') }}</h1>

    @can('create', App\Models\JobTemplate::class)
        <x-ui.button :href="route('job-templates.create')">{{ __('templates.index.new') }}</x-ui.button>
    @endcan
</div>
