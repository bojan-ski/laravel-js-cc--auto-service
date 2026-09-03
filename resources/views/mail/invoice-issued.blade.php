<div style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.5; color: #1C1917;">
    <p>{{ __('mail.invoice_issued.greeting', ['name' => $customer->name]) }}</p>

    <p>{{ __('mail.invoice_issued.body', ['make' => $vehicle->make, 'model' => $vehicle->model, 'plate' => $vehicle->registration_plate]) }}</p>

    <p>
        {{ __('mail.invoice_issued.number', ['number' => $invoice->number]) }}<br>
        {{ __('mail.invoice_issued.issued', ['date' => $invoice->issued_at->format('j M Y')]) }}
        @if ($invoice->due_at !== null)
            <br>{{ __('mail.invoice_issued.due', ['date' => $invoice->due_at->format('j M Y')]) }}
        @endif
    </p>

    <p>
        {{ __('mail.invoice_issued.subtotal', ['amount' => $money['subtotal']]) }}<br>
        {{ __('mail.invoice_issued.vat', ['rate' => $money['rate'], 'amount' => $money['tax']]) }}<br>
        <strong>{{ __('mail.invoice_issued.total', ['amount' => $money['total']]) }}</strong>
    </p>

    <x-mail.shop-footer />
</div>
