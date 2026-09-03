<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('staff.title') }}</h1>

    @can('create', App\Models\User::class)
        <x-ui.button :href="route('admin.users.create')">{{ __('staff.new') }}</x-ui.button>
    @endcan
</div>
