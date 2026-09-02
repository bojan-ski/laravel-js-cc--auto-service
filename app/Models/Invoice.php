<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceTransition;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    #[Scope]
    protected function joinedForList(Builder $query): void
    {
        $query
            ->join('jobs', 'jobs.id', '=', 'invoices.job_id')
            ->join('customers', 'customers.id', '=', 'jobs.customer_id')
            ->select('invoices.*');
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $query) use ($escaped): void {
            $query->where('invoices.number', 'like', "%{$escaped}%")
                ->orWhere('customers.name', 'like', "%{$escaped}%");
        });
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

    public function canBeMarkedPaid(): bool
    {
        return $this->isUnpaid();
    }

    public function canBeVoided(): bool
    {
        return $this->isUnpaid() || $this->isPaid();
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
