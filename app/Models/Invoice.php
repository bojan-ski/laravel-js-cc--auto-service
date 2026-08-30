<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceTransition;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice): void {
            if (! $invoice->exists) {
                return;
            }

            if (! array_key_exists('status', $invoice->getRawOriginal())) {
                throw new InvalidInvoiceTransition(
                    $invoice,
                    $invoice->getDirty(),
                    'the stored status was not read, so the void guard cannot be applied',
                );
            }

            if ($invoice->getOriginal('status') !== InvoiceStatus::Void) {
                return;
            }

            $changes = $invoice->getDirty();

            if ($changes !== []) {
                throw new InvalidInvoiceTransition($invoice, $changes, 'a voided invoice is final');
            }
        });
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function isUnpaid(): bool
    {
        return $this->status === InvoiceStatus::Unpaid;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isVoid(): bool
    {
        return $this->status === InvoiceStatus::Void;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
