<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('jobs.board.title') }}</h1>

    @can('create', App\Models\Job::class)
        <x-ui.button :href="route('jobs.create')">{{ __('jobs.board.new') }}</x-ui.button>
    @endcan
</div>
