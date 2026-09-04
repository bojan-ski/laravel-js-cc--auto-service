@props(['brands'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('brands.count', $brands->total(), ['count' => $brands->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-brands.table-head />

        <tbody role="rowgroup">
            @foreach ($brands as $brand)
                <x-brands.row :brand="$brand" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $brands->links() }}</div>
