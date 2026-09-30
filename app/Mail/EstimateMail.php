<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLine;
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
class EstimateMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public bool $afterCommit = true;

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
     * @return array{rate: string, subtotal: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function money(): array
    {
        return [
            'rate' => $this->estimate->taxRateLabel(),
            'subtotal' => $this->format($this->estimate->subtotal),
            'tax' => $this->format($this->estimate->tax_amount),
            'total' => $this->format($this->estimate->total),
            'discount' => $this->format($this->estimate->discount_total),
            'before_discount' => $this->format(bcadd($this->estimate->subtotal, $this->estimate->discount_total, 2)),
            'has_discount' => $this->estimate->hasDiscount(),
        ];
    }

    /**
     * @return list<array{description: string, quantity: string, percent: ?string, total: string}>
     */
    public function lines(): array
    {
        return $this->estimate->lines()->get()
            ->map(fn (EstimateLine $line): array => [
                'description' => $line->description,
                'quantity' => $this->trim($line->quantity),
                'percent' => $line->hasDiscount() ? $line->discountPercentLabel() : null,
                'total' => $this->format($line->lineTotal()),
            ])
            ->values()
            ->all();
    }

    private function format(string $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function trim(string $quantity): string
    {
        $trimmed = rtrim(rtrim($quantity, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer email failed to send.', [
            'mailable' => self::class,
            'recipient' => $this->customer->email,
            'record' => $this->estimate->number,
            'reason' => $exception->getMessage(),
        ]);
    }
}
