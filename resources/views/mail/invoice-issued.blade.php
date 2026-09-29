@php
    $body = __('mail.invoice_issued.body', [
        'make' => $vehicle->make,
        'model' => $vehicle->model,
        'plate' => $vehicle->registration_plate,
    ]);
@endphp

<x-mail.layout
    :title="__('mail.invoice_issued.subject', ['number' => $invoice->number, 'plate' => $vehicle->registration_plate])"
    :heading="__('mail.invoice_issued.heading', ['number' => $invoice->number])"
    :preheader="$body"
>
    <p style="margin: 0 0 12px;">{{ __('mail.invoice_issued.greeting', ['name' => $invoice->buyer_name]) }}</p>

    <p style="margin: 0;">{{ $body }}</p>

    <x-mail.vehicle :vehicle="$vehicle" />

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <x-mail.row :label="__('mail.invoice_issued.number')" :value="$invoice->number" />

        @if ($invoice->hasCompanyBuyer())
            <x-mail.row :label="__('mail.invoice_issued.tax_id')" :value="$invoice->buyer_tax_id" />
            <x-mail.row :label="__('mail.invoice_issued.registration_number')" :value="$invoice->buyer_registration_number" />
        @endif

        <x-mail.row :label="__('mail.invoice_issued.issued')" :value="$invoice->issued_at->translatedFormat('j M Y')" />

        @if ($invoice->due_at !== null)
            <x-mail.row :label="__('mail.invoice_issued.due')" :value="$invoice->due_at->translatedFormat('j M Y')" />
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
        <x-mail.row :label="__('mail.invoice_issued.subtotal')" :value="$money['subtotal']" />
        <x-mail.row :label="__('mail.invoice_issued.vat', ['rate' => $money['rate']])" :value="$money['tax']" />
        <x-mail.row :label="__('mail.invoice_issued.total')" :value="$money['total']" total />
    </table>
</x-mail.layout>
