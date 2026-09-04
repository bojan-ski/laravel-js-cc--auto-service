@props(['brand'])

<tr role="row" @class([
    'grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0',
    'bg-surface-muted text-text-muted' => $brand->trashed(),
])>
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        <span class="font-medium">{{ $brand->name }}</span>

        @if ($brand->trashed())
            <span class="block text-xs font-medium uppercase tracking-wider">{{ __('brands.trashed.badge') }}</span>
        @endif
    </td>

    <td role="cell" class="col-start-1 row-start-2 md:table-cell md:px-3 md:py-2">
        {{ $brand->quality_tier->label() }}
    </td>

    <td role="cell" class="hidden tabular-nums lg:table-cell lg:px-3 lg:py-2">
        {{ trans_choice('brands.parts_count', $brand->parts_count, ['count' => $brand->parts_count]) }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-2 md:text-start">
        <div class="flex flex-wrap items-center justify-end gap-2 md:justify-start">
            @can('restore', $brand)
                <x-ui.action-form
                    :action="route('admin.brands.restore', $brand)"
                    :confirm="__('brands.restore.confirm', ['name' => $brand->name])"
                    :aria-label="__('brands.restore.action', ['name' => $brand->name])"
                >
                    {{ __('brands.restore.label') }}
                </x-ui.action-form>
            @endcan

            @can('update', $brand)
                <x-ui.button
                    variant="secondary"
                    :href="route('admin.brands.edit', $brand)"
                    :aria-label="__('brands.edit.action', ['name' => $brand->name])"
                >
                    {{ __('app.actions.edit') }}
                </x-ui.button>
            @endcan

            @can('delete', $brand)
                <x-ui.action-form
                    method="DELETE"
                    variant="danger"
                    :action="route('admin.brands.destroy', $brand)"
                    :confirm="__('brands.delete.confirm', ['name' => $brand->name])"
                    :aria-label="__('brands.delete.action', ['name' => $brand->name])"
                >
                    {{ __('app.actions.delete') }}
                </x-ui.action-form>
            @endcan
        </div>
    </td>
</tr>
