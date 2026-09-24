@props(['parts'])

<p class="mt-4 text-sm text-text-muted tabular-nums">
    {{ trans_choice('parts.count', $parts->total(), ['count' => $parts->total()]) }}
</p>

<div class="mt-2 overflow-x-auto border border-border bg-surface md:overflow-x-visible">
    <table role="table" class="w-full text-left">
        <x-parts.table-head />

        <tbody role="rowgroup">
            @foreach ($parts as $part)
                <x-parts.row :part="$part" />
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $parts->links() }}</div>
