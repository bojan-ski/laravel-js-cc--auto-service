{{ __('mail.invoice_issued.greeting', ['name' => $invoice->buyer_name]) }}

{{ __('mail.invoice_issued.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}

{{ __('mail.pair', ['label' => __('mail.invoice_issued.number'), 'value' => $invoice->number]) }}
@if ($invoice->hasCompanyBuyer())
{{ __('mail.pair', ['label' => __('mail.invoice_issued.tax_id'), 'value' => $invoice->buyer_tax_id]) }}
{{ __('mail.pair', ['label' => __('mail.invoice_issued.registration_number'), 'value' => $invoice->buyer_registration_number]) }}
@endif
{{ __('mail.pair', ['label' => __('mail.invoice_issued.issued'), 'value' => $invoice->issued_at->translatedFormat('j M Y')]) }}
@if ($invoice->due_at !== null)
{{ __('mail.pair', ['label' => __('mail.invoice_issued.due'), 'value' => $invoice->due_at->translatedFormat('j M Y')]) }}
@endif

<x-mail.totals-text :money="$money" />

<x-mail.shop-footer-text />
