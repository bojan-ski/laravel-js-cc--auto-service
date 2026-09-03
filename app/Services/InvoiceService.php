<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Exceptions\InvalidInvoiceTransition;
use App\Exceptions\InvoiceNotIssuable;
use App\Mail\InvoiceIssuedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public const int NUMBER_ATTEMPTS = 3;

    public const int PER_PAGE = 25;

    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly CustomerMailer $mailer,
    ) {}

    /**
     * @param  array{status: string, search: string}  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateFiltered(array $filters): LengthAwarePaginator
    {
        return $this->listQuery($filters['search'])
            ->with('job.customer')
            ->when(
                $filters['status'],
                fn (Builder $query, string $status) => $query->where('invoices.status', $status),
            )
            ->orderByDesc('invoices.issued_at')
            ->orderByDesc('invoices.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{status: string, search: string}  $filters
     * @return array<string, int>
     */
    public function countByStatus(array $filters): array
    {
        $counts = $this->listQuery($filters['search'])
            ->select('invoices.status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('invoices.status')
            ->pluck('aggregate', 'invoices.status');

        $result = [];

        foreach (InvoiceStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    public function hasAny(): bool
    {
        return Invoice::query()->exists();
    }

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

            $this->announceIssue($invoice, $locked);

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBeMarkedPaid()) {
                throw new InvalidInvoiceTransition(
                    $locked,
                    ['status' => InvoiceStatus::Paid->value],
                    'only an unpaid invoice can be marked paid',
                );
            }

            $locked->status = InvoiceStatus::Paid;
            $locked->paid_at = now();
            $locked->save();

            $invoice->setRawAttributes($locked->getAttributes(), true);

            return $invoice;
        });
    }

    public function void(Invoice $invoice, User $voidedBy): Invoice
    {
        return DB::transaction(function () use ($invoice, $voidedBy): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $job = Job::query()->whereKey($locked->job_id)->lockForUpdate()->firstOrFail();

            if (! $locked->canBeVoided()) {
                throw new InvalidInvoiceTransition(
                    $locked,
                    ['status' => InvoiceStatus::Void->value],
                    'only an unpaid or paid invoice can be voided',
                );
            }

            $locked->status = InvoiceStatus::Void;
            $locked->voided_at = now();
            $locked->voided_by = $voidedBy->getKey();
            $locked->save();

            $job->locked_at = null;
            $job->save();

            $invoice->setRawAttributes($locked->getAttributes(), true);

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

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string}
     */
    public function storedTotals(Invoice $invoice): array
    {
        return [
            'subtotal' => $invoice->subtotal,
            'rate' => $invoice->taxRateLabel(),
            'tax' => $invoice->tax_amount,
            'total' => $invoice->total,
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

    /**
     * @return Builder<Invoice>
     */
    private function listQuery(string $search): Builder
    {
        return Invoice::query()
            ->joinedForList()
            ->when($search, fn (Builder $query, string $term) => $query->matching($term));
    }

    private function announceIssue(Invoice $invoice, Job $job): void
    {
        $customer = Customer::withTrashed()->findOrFail($job->customer_id);
        $vehicle = Vehicle::withTrashed()->findOrFail($job->vehicle_id);

        $this->mailer->send($customer, new InvoiceIssuedMail($invoice, $customer, $vehicle));
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
