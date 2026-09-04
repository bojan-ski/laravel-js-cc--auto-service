@props(['part', 'number'])

<li class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 last:border-b-0">
    <span>
        <span class="text-xs font-medium uppercase tracking-wider text-text-muted">{{ $number->type->label() }}</span>
        <span class="ml-2 font-medium tabular-nums">{{ $number->number }}</span>
    </span>

    @can('update', $part)
        <x-ui.action-form
            method="DELETE"
            variant="danger"
            :action="route('parts.numbers.destroy', [$part, $number])"
            :confirm="__('parts.numbers.confirm_remove', ['number' => $number->number])"
            :aria-label="__('parts.numbers.delete_action', ['number' => $number->number])"
        >
            {{ __('app.actions.delete') }}
        </x-ui.action-form>
    @endcan
</li>
