<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vehicle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(30)]
#[Backoff(60)]
class InvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public bool $afterCommit = true;

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
     * @return array{rate: string, subtotal: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function money(): array
    {
        return [
            'rate' => $this->invoice->taxRateLabel(),
            'subtotal' => $this->format($this->invoice->subtotal),
            'tax' => $this->format($this->invoice->tax_amount),
            'total' => $this->format($this->invoice->total),
            'discount' => $this->format($this->invoice->discount_total),
            'before_discount' => $this->format(bcadd($this->invoice->subtotal, $this->invoice->discount_total, 2)),
            'has_discount' => $this->invoice->hasDiscount(),
        ];
    }

    private function format(string $amount): string
    {
        return number_format((float) $amount, 2);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer email failed to send.', [
            'mailable' => self::class,
            'recipient' => $this->customer->email,
            'record' => $this->invoice->number,
            'reason' => $exception->getMessage(),
        ]);
    }
}
