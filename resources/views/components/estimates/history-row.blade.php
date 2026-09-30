@props(['estimate', 'current' => false])

<tr role="row" class="relative grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 hover:bg-surface-muted md:table-row md:p-0">
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-1.5">
        <a
            href="{{ route('estimates.show', $estimate) }}"
            @if ($current) aria-current="page" @endif
            class="font-medium tabular-nums underline underline-offset-2 after:absolute after:inset-0 hover:text-brand-dark"
        >
            {{ $estimate->number }}
        </a>
    </td>

    <td role="cell" class="col-start-1 row-start-2 md:table-cell md:px-3 md:py-1.5">
        <x-ui.status-badge :status="$estimate->status" />
    </td>

    <td role="cell" class="hidden tabular-nums md:table-cell md:px-3 md:py-1.5">
        {{ $estimate->created_at->isoFormat('D MMM YYYY') }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 row-span-2 self-center text-end md:table-cell md:px-3 md:py-1.5">
        <x-ui.money :value="$estimate->total" />
    </td>
</tr>
