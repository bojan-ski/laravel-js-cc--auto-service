@props(['template', 'showActions'])

<tr role="row" @class([
    'relative grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0',
    'hover:bg-surface-muted' => ! $template->trashed(),
    'bg-surface-muted text-text-muted' => $template->trashed(),
])>
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        @if ($template->trashed())
            <span class="font-medium">{{ $template->name }}</span>
            <span class="block text-xs font-medium uppercase tracking-wider">{{ __('templates.trashed.badge') }}</span>
        @else
            <a
                href="{{ route('job-templates.edit', $template) }}"
                class="block font-medium underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
            >{{ $template->name }}</a>
        @endif
    </td>

    <td role="cell" class="hidden text-text-soft md:table-cell md:px-3 md:py-2">
        <span class="line-clamp-2"><x-ui.value :value="$template->description" /></span>
    </td>

    <td role="cell" class="col-start-2 row-start-1 text-end tabular-nums md:table-cell md:px-3 md:py-2 md:text-start">
        @if ($template->items_count === 0)
            <span class="text-text-muted">{{ __('templates.no_lines') }}</span>
        @else
            {{ trans_choice('templates.lines_count', $template->items_count, ['count' => $template->items_count]) }}
        @endif
    </td>

    @if ($showActions)
        <td role="cell" class="col-start-1 row-start-2 md:table-cell md:px-3 md:py-2">
            @can('restore', $template)
                <x-ui.action-form
                    :action="route('job-templates.restore', $template)"
                    :confirm="__('templates.restore.confirm', ['name' => $template->name])"
                    :aria-label="__('templates.restore.action', ['name' => $template->name])"
                >
                    {{ __('templates.restore.label') }}
                </x-ui.action-form>
            @endcan
        </td>
    @endif
</tr>
