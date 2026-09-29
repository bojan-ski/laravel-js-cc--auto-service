@props(['invoice'])

@php
    $void = $invoice->isVoid();
@endphp

<tr
    role="row"
    @class([
        'grid grid-cols-[1fr_auto] items-center gap-x-3 border-b border-border p-3 last:border-b-0 md:table-row md:p-0',
        'text-text-muted' => $void,
    ])
>
    <td role="cell" class="col-start-1 row-start-1 md:table-cell md:px-3 md:py-2">
        <a
            href="{{ route('invoices.show', $invoice) }}"
            class="block font-medium tabular-nums underline underline-offset-2 hover:text-brand-dark"
        >
            {{ $invoice->number }}
        </a>
    </td>

    <td role="cell" class="col-start-1 row-start-2 text-sm md:table-cell md:px-3 md:py-2 md:text-base">
        <x-invoices.buyer :invoice="$invoice" />
    </td>

    <td role="cell" class="hidden tabular-nums xl:table-cell xl:px-3 xl:py-2">
        {{ $invoice->issued_at->isoFormat('D MMM YYYY') }}
    </td>

    <td role="cell" class="col-start-2 row-start-1 text-end md:table-cell md:px-3 md:py-2">
        <x-ui.money :value="$invoice->total" :class="$void ? 'line-through' : null" />
    </td>

    <td role="cell" class="col-start-2 row-start-2 self-center text-end md:table-cell md:px-3 md:py-2 md:text-start">
        <x-ui.status-badge :status="$invoice->status" />
    </td>
</tr>
