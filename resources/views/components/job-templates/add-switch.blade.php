@props(['template', 'adding'])

<div role="group" aria-label="{{ __('templates.lines.add.group_label') }}" class="flex flex-wrap gap-2">
    <x-jobs.add-link
        :href="route('job-templates.edit', ['jobTemplate' => $template, 'add' => 'labor']).'#template-line-form'"
        :active="$adding === 'labor'"
    >
        {{ __('templates.lines.add.labor_link') }}
    </x-jobs.add-link>

    <x-jobs.add-link
        :href="route('job-templates.edit', ['jobTemplate' => $template, 'add' => 'part']).'#template-line-form'"
        :active="$adding === 'part'"
    >
        {{ __('templates.lines.add.part_link') }}
    </x-jobs.add-link>
</div>
