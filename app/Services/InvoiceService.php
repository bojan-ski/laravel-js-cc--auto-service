<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Exceptions\InvoiceNotIssuable;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public const int NUMBER_ATTEMPTS = 3;

    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    public function issue(Job $job, User $issuedBy, ?CarbonInterface $dueAt = null): Invoice
    {
        return $this->persist(function () use ($job, $issuedBy, $dueAt): Invoice {
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $locked->load('items');

            $this->refuseUnlessIssuable($locked);

            $totals = $this->totalsFor($locked);

            $invoice = new Invoice;
            $invoice->job_id = $locked->getKey();
            $invoice->number = $this->numbers->generate(now());
            $invoice->status = InvoiceStatus::Unpaid;
            $invoice->subtotal = $totals['subtotal'];
            $invoice->tax_rate = $totals['rate'];
            $invoice->tax_amount = $totals['tax'];
            $invoice->total = $totals['total'];
            $invoice->issued_at = now();
            $invoice->due_at = $dueAt;
            $invoice->paid_at = null;
            $invoice->voided_at = null;
            $invoice->voided_by = null;
            $invoice->issued_by = $issuedBy->getKey();
            $invoice->save();

            $locked->locked_at = now();
            $locked->save();

            $job->setRawAttributes($locked->getAttributes(), true);

            return $invoice;
        });
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string}
     */
    public function totalsFor(Job $job): array
    {
        $subtotal = $job->subtotal();
        $rate = $this->rate();
        $tax = self::taxOn($subtotal, $rate);

        return [
            'subtotal' => $subtotal,
            'rate' => $rate,
            'tax' => $tax,
            'total' => bcadd($subtotal, $tax, 2),
        ];
    }

    public function rate(): string
    {
        return (string) config('shop.vat_rate');
    }

    private static function taxOn(string $subtotal, string $rate): string
    {
        return bcadd(bcdiv(bcmul($subtotal, $rate, 4), '100', 4), '0.005', 2);
    }

    private function refuseUnlessIssuable(Job $job): void
    {
        if ($job->status !== JobStatus::Completed) {
            throw new InvoiceNotIssuable($job, InvoiceNotIssuable::NOT_COMPLETED);
        }

        if ($job->isLocked()) {
            throw new InvoiceNotIssuable($job, InvoiceNotIssuable::LOCKED);
        }

        if (! $job->hasLineItems()) {
            throw new InvoiceNotIssuable($job, InvoiceNotIssuable::NO_LINE_ITEMS);
        }

        if ($job->hasActiveInvoice()) {
            throw new InvoiceNotIssuable($job, InvoiceNotIssuable::ALREADY_INVOICED);
        }
    }

    /**
     * @param  Closure(): Invoice  $issuing
     */
    private function persist(Closure $issuing): Invoice
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($issuing);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }
}
