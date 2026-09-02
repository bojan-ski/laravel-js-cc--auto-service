@props(['invoice'])

@php
    $customer = $invoice->job->customer;
@endphp

<div class="hidden print:block">
    <div class="flex items-start justify-between gap-6">
        <div>
            <p class="text-lg font-bold">{{ config('shop.identity.name') }}</p>
            <p>{{ config('shop.identity.address') }}</p>
            <p class="tabular-nums">{{ config('shop.identity.tax_number') }}</p>
        </div>

        <div class="text-end">
            <p class="font-semibold tabular-nums">
                {{ __('invoices.identity.heading', ['number' => $invoice->number]) }}
            </p>

            <p class="tabular-nums">
                {{ __('invoices.identity.issued', ['date' => $invoice->issued_at->isoFormat('D MMM YYYY')]) }}
            </p>

            @if ($invoice->isVoid())
                <p class="mt-2 border-2 border-danger px-3 py-1 text-2xl font-bold uppercase tracking-widest text-danger">
                    {{ __('invoices.void.print_marker') }}
                </p>
            @endif
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs font-medium uppercase tracking-wider">{{ __('invoices.identity.bill_to') }}</p>

        <p class="font-medium">{{ $customer->name }}</p>

        @if (filled($customer->address))
            <p>{{ $customer->address }}</p>
        @endif
    </div>
</div>
