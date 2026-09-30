@props(['templates', 'showActions'])

<div class="mt-4 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-job-templates.table-head :show-actions="$showActions" />

        <tbody role="rowgroup">
            @foreach ($templates as $template)
                <x-job-templates.row :template="$template" :show-actions="$showActions" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $templates->links() }}</div>
