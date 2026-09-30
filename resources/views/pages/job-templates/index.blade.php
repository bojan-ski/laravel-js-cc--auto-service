<x-layouts.app :title="__('templates.index.title')">
    <x-job-templates.index-header />

    @if ($templates->total() > 0)
        <x-job-templates.table :templates="$templates" :show-actions="$showActions" />
    @else
        <x-ui.empty-state
            class="mt-4"
            :title="__('templates.empty.none.title')"
            :body="__('templates.empty.none.body')"
        >
            @can('create', App\Models\JobTemplate::class)
                <x-ui.button :href="route('job-templates.create')">{{ __('templates.index.new') }}</x-ui.button>
            @endcan
        </x-ui.empty-state>
    @endif
</x-layouts.app>
