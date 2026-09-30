@props(['template'])

<div class="mt-6 max-w-4xl border-t border-border pt-4">
    <x-ui.action-form
        method="DELETE"
        variant="danger"
        :action="route('job-templates.destroy', $template)"
        :confirm="__('templates.delete.confirm', ['name' => $template->name])"
        :aria-label="__('templates.delete.action', ['name' => $template->name])"
    >
        {{ __('templates.delete.label') }}
    </x-ui.action-form>
</div>
