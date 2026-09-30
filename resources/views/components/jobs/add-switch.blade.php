@props(['job', 'adding'])

<div role="group" aria-label="{{ __('jobs.items.add.group_label') }}" class="flex flex-wrap gap-2">
    <x-jobs.add-link
        :href="route('jobs.show', ['job' => $job, 'add' => 'labor']).'#line-item-form'"
        :active="$adding === 'labor'"
        :aria-label="__('jobs.items.add.labor_link_label')"
    >
        {{ __('jobs.items.add.labor_link') }}
    </x-jobs.add-link>

    <x-jobs.add-link
        :href="route('jobs.show', ['job' => $job, 'add' => 'part']).'#line-item-form'"
        :active="$adding === 'part'"
        :aria-label="__('jobs.items.add.part_link_label')"
    >
        {{ __('jobs.items.add.part_link') }}
    </x-jobs.add-link>

    <x-jobs.add-link
        :href="route('jobs.show', ['job' => $job, 'add' => 'template']).'#line-item-form'"
        :active="$adding === 'template'"
        :aria-label="__('templates.apply.link_label')"
    >
        {{ __('templates.apply.link') }}
    </x-jobs.add-link>
</div>
