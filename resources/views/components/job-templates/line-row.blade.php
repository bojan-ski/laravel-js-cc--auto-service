@props(['template', 'line', 'editing' => false])

@php
    $context = ['description' => $line->displayName()];
    $control = 'px-2 py-1 text-xs';
@endphp

<tr
    role="row"
    @if ($editing) aria-current="true" @endif
    @class(['border-b border-border last:border-b-0', 'bg-surface-muted' => $editing])
>
    <td role="cell" class="hidden px-3 py-2 md:table-cell">{{ $line->type->label() }}</td>

    <td role="cell" class="px-3 py-2">
        @if ($line->isPart())
            {{ __('templates.lines.part_label', ['name' => $line->part->name, 'sku' => $line->part->sku]) }}

            @if ($line->hasDeletedPart())
                <span class="text-sm font-medium text-status-cancelled">{{ __('templates.lines.part_deleted') }}</span>
            @endif
        @else
            {{ $line->description }}
        @endif

        @if ($editing)
            <span class="sr-only">{{ __('templates.lines.edit.row_marker') }}</span>
        @endif
    </td>

    <td role="cell" class="px-3 py-2 text-end">
        <x-ui.quantity :value="$line->quantity" />
    </td>

    <td role="cell" class="px-3 py-2">
        <div class="flex justify-end gap-2">
            <x-ui.button
                variant="secondary"
                :href="route('job-templates.edit', ['jobTemplate' => $template, 'edit' => $line->getKey()]).'#template-line-form'"
                :aria-label="__('templates.lines.edit.open_line', $context)"
                :class="$control"
            >
                {{ __('templates.lines.edit.open') }}
            </x-ui.button>

            <x-ui.action-form
                method="DELETE"
                variant="danger"
                :action="route('job-templates.items.destroy', ['jobTemplate' => $template, 'item' => $line])"
                :confirm="__('templates.lines.remove.confirm', $context)"
                :aria-label="__('templates.lines.remove.action', $context)"
                :class="$control"
            >
                {{ __('templates.lines.remove.label') }}
            </x-ui.action-form>
        </div>
    </td>
</tr>
