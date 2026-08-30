@props(['part'])

<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">
        <span class="tabular-nums">{{ $part->sku }}</span>
        <span class="text-text-muted">·</span>
        {{ $part->name }}

        @if ($part->trashed())
            <span class="ms-1 align-middle text-xs font-semibold uppercase tracking-wider text-text-muted">
                {{ __('parts.trashed.badge') }}
            </span>
        @endif
    </h1>

    <div class="flex flex-wrap gap-2">
        @if ($part->trashed())
            @can('restore', $part)
                <x-ui.action-form
                    :action="route('parts.restore', $part)"
                    :confirm="__('parts.restore.confirm', ['sku' => $part->sku])"
                    :aria-label="__('parts.restore.action', ['sku' => $part->sku])"
                >
                    {{ __('parts.restore.label') }}
                </x-ui.action-form>
            @endcan
        @else
            @can('update', $part)
                <x-ui.button
                    variant="secondary"
                    :href="route('parts.edit', $part)"
                    :aria-label="__('parts.edit.action', ['sku' => $part->sku])"
                >
                    {{ __('app.actions.edit') }}
                </x-ui.button>
            @endcan

            @can('delete', $part)
                <x-ui.action-form
                    method="DELETE"
                    variant="danger"
                    :action="route('parts.destroy', $part)"
                    :confirm="__('parts.delete.confirm', ['sku' => $part->sku])"
                    :aria-label="__('parts.delete.action', ['sku' => $part->sku])"
                >
                    {{ __('app.actions.delete') }}
                </x-ui.action-form>
            @endcan
        @endif
    </div>
</div>
