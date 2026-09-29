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

@if ($money['has_discount'])
{{ __('mail.pair', ['label' => __('mail.invoice_issued.before_discount'), 'value' => $money['before_discount']]) }}
{{ __('mail.pair', ['label' => __('mail.invoice_issued.discount'), 'value' => $money['discount']]) }}
@endif
{{ __('mail.pair', ['label' => __('mail.invoice_issued.subtotal'), 'value' => $money['subtotal']]) }}
{{ __('mail.pair', ['label' => __('mail.invoice_issued.vat', ['rate' => $money['rate']]), 'value' => $money['tax']]) }}
{{ __('mail.pair', ['label' => __('mail.invoice_issued.total'), 'value' => $money['total']]) }}

<x-mail.shop-footer-text />
