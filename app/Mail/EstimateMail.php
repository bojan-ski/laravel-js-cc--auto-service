<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Vehicle;
use App\Services\Money;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EstimateMail extends CustomerMailable
{
    public function __construct(
        public Estimate $estimate,
        public Customer $customer,
        public Vehicle $vehicle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.estimate.subject', [
                'number' => $this->estimate->number,
                'plate' => $this->vehicle->registration_plate,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.estimate',
            text: 'mail.estimate-text',
            with: ['money' => $this->money(), 'lines' => $this->lines()],
        );
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function money(): array
    {
        return Money::formatTotals($this->estimate->storedTotals());
    }

    /**
     * @return list<array{description: string, quantity: string, percent: ?string, total: string}>
     */
    public function lines(): array
    {
        return $this->estimate->lines()->get()
            ->map(fn (EstimateLine $line): array => [
                'description' => $line->description,
                'quantity' => Money::trimZeros($line->quantity),
                'percent' => $line->hasDiscount() ? $line->discountPercentLabel() : null,
                'total' => Money::format($line->lineTotal()),
            ])
            ->values()
            ->all();
    }

    protected function recipient(): Customer
    {
        return $this->customer;
    }

    protected function record(): string
    {
        return $this->estimate->number;
    }
}
