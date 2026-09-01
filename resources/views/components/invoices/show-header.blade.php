@props(['invoice'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <h1 class="text-2xl font-bold tabular-nums print:hidden">{{ $invoice->number }}</h1>

    <x-ui.status-badge :status="$invoice->status" />
</div>
