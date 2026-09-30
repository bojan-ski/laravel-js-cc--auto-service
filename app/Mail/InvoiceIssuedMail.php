<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vehicle;
use App\Services\Money;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceIssuedMail extends CustomerMailable
{
    public function __construct(
        public Invoice $invoice,
        public Customer $customer,
        public Vehicle $vehicle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.invoice_issued.subject', [
                'number' => $this->invoice->number,
                'plate' => $this->vehicle->registration_plate,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice-issued',
            text: 'mail.invoice-issued-text',
            with: ['money' => $this->money()],
        );
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function money(): array
    {
        return Money::formatTotals($this->invoice->storedTotals());
    }

    protected function recipient(): Customer
    {
        return $this->customer;
    }

    protected function record(): string
    {
        return $this->invoice->number;
    }
}
