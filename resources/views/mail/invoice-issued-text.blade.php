{{ __('mail.invoice_issued.greeting', ['name' => $customer->name]) }}

{{ __('mail.invoice_issued.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}

{{ __('mail.invoice_issued.number', ['number' => $invoice->number]) }}
{{ __('mail.invoice_issued.issued', ['date' => $invoice->issued_at->format('j M Y')]) }}
@if ($invoice->due_at !== null)
{{ __('mail.invoice_issued.due', ['date' => $invoice->due_at->format('j M Y')]) }}
@endif

{{ __('mail.invoice_issued.subtotal', ['amount' => $money['subtotal']]) }}
{{ __('mail.invoice_issued.vat', ['rate' => $money['rate'], 'amount' => $money['tax']]) }}
{{ __('mail.invoice_issued.total', ['amount' => $money['total']]) }}

<x-mail.shop-footer-text />
