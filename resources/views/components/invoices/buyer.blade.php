@props(['invoice'])

@php
    $record = $invoice->job->customer;
@endphp

@if ($record->trashed())
    <span>
        {{ $invoice->buyer_name }}
        <span class="text-sm text-text-muted">({{ __('app.deleted') }})</span>
    </span>
@else
    <a href="{{ route('customers.show', $record) }}" class="font-medium underline underline-offset-2 hover:text-brand-dark">
        {{ $invoice->buyer_name }}
    </a>
@endif
