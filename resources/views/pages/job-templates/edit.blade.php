<x-layouts.app :title="__('templates.edit.title', ['name' => $template->name])">
    <h1 class="text-2xl font-bold">{{ __('templates.edit.title', ['name' => $template->name]) }}</h1>

    <x-job-templates.form
        :template="$template"
        method="PUT"
        :action="route('job-templates.update', $template)"
        :submit="__('templates.edit.submit')"
    />

    <x-job-templates.lines
        :template="$template"
        :lines="$lines"
        :editing="$editing"
        :adding="$adding"
        :part-options="$partOptions"
        :has-deleted-part="$hasDeletedPart"
    />

    @can('delete', $template)
        <x-job-templates.delete-form :template="$template" />
    @endcan
</x-layouts.app>
