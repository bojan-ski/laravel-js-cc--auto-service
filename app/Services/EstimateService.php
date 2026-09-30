<?php

namespace App\Services;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\EstimateNotCreatable;
use App\Exceptions\InvalidEstimateTransition;
use App\Mail\EstimateMail;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EstimateService
{
    public const int NUMBER_ATTEMPTS = 3;

    public function __construct(
        private InvoiceService $invoices,
        private EstimateNumberGenerator $numbers,
        private CustomerMailer $mailer,
    ) {}

    public function create(Job $job, User $createdBy): Estimate
    {
        return $this->persist(function () use ($job, $createdBy): Estimate {
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $locked->load(['items' => fn ($query) => $query->orderBy('id')]);

            $this->refuseUnlessEstimable($locked);
            $this->supersedeOpenEstimate($locked);

            $totals = $this->invoices->totalsFor($locked);

            $estimate = new Estimate;
            $estimate->job_id = $locked->getKey();
            $estimate->number = $this->numbers->generate(now());
            $estimate->status = EstimateStatus::Pending;
            $estimate->subtotal = $totals['subtotal'];
            $estimate->discount_total = $totals['discount'];
            $estimate->tax_rate = $totals['rate'];
            $estimate->tax_amount = $totals['tax'];
            $estimate->total = $totals['total'];
            $estimate->created_by = $createdBy->getKey();
            $estimate->save();

            foreach ($locked->items->values() as $index => $item) {
                $line = new EstimateLine;
                $line->estimate_id = $estimate->getKey();
                $line->type = $item->type;
                $line->description = $item->description;
                $line->quantity = $item->quantity;
                $line->unit_price = $item->unit_price;
                $line->discount_percent = $item->discount_percent;
                $line->line_total = $item->lineTotal();
                $line->position = $index + 1;
                $line->save();
            }

            return $estimate;
        });
    }

    public function decide(Estimate $estimate, EstimateStatus $decision, DecisionMethod $method, ?string $note, User $decidedBy): Estimate
    {
        if (! in_array($decision, [EstimateStatus::Approved, EstimateStatus::Declined], true)) {
            throw new InvalidEstimateTransition($estimate, ['status' => $decision->value], 'a decision is either approved or declined');
        }

        return DB::transaction(function () use ($estimate, $decision, $method, $note, $decidedBy): Estimate {
            $locked = Estimate::query()->whereKey($estimate->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBeDecided()) {
                throw new InvalidEstimateTransition($locked, ['status' => $decision->value], 'only a pending estimate can be decided');
            }

            $locked->status = $decision;
            $locked->decision_method = $method;
            $locked->decided_at = now();
            $locked->decided_by = $decidedBy->getKey();
            $locked->decision_note = $note;
            $locked->save();

            $estimate->setRawAttributes($locked->getAttributes(), true);

            return $estimate;
        });
    }

    public function email(Estimate $estimate): bool
    {
        return DB::transaction(function () use ($estimate): bool {
            $locked = Estimate::query()->whereKey($estimate->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBeEmailed()) {
                throw new InvalidEstimateTransition($locked, ['emailed_at' => now()], 'only a pending or approved estimate can be emailed');
            }

            $job = Job::query()->select(['id', 'customer_id', 'vehicle_id'])->findOrFail($locked->job_id);
            $customer = Customer::withTrashed()->findOrFail($job->customer_id);
            $vehicle = Vehicle::withTrashed()->findOrFail($job->vehicle_id);

            if (! $this->mailer->send($customer, new EstimateMail($locked, $customer, $vehicle))) {
                return false;
            }

            $locked->emailed_at = now();
            $locked->save();

            $estimate->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function storedTotals(Estimate $estimate): array
    {
        return [
            'subtotal' => $estimate->subtotal,
            'rate' => $estimate->taxRateLabel(),
            'tax' => $estimate->tax_amount,
            'total' => $estimate->total,
            'discount' => $estimate->discount_total,
            'before_discount' => bcadd($estimate->subtotal, $estimate->discount_total, 2),
            'has_discount' => $estimate->hasDiscount(),
        ];
    }

    /**
     * @return array{estimate: Estimate, warning: 'over'|'not_approved'|'declined'|null, over_by: ?string}|null
     */
    public function summaryFor(Job $job): ?array
    {
        $estimate = $job->currentEstimate()->first();

        if ($estimate === null) {
            return null;
        }

        $overBy = null;

        if ($estimate->isApproved()) {
            $live = $this->invoices->totalsFor($job)['total'];
            $overBy = bccomp($live, $estimate->total, 2) > 0 ? bcsub($live, $estimate->total, 2) : null;
        }

        return [
            'estimate' => $estimate,
            'warning' => match (true) {
                $estimate->isPending() => 'not_approved',
                $estimate->isDeclined() => 'declined',
                $overBy !== null => 'over',
                default => null,
            },
            'over_by' => $overBy,
        ];
    }

    /**
     * @return Collection<int, Estimate>
     */
    public function historyFor(Estimate $estimate): Collection
    {
        return Estimate::query()
            ->select(['id', 'job_id', 'number', 'status', 'total', 'created_at'])
            ->where('job_id', $estimate->job_id)
            ->orderByDesc('id')
            ->get();
    }

    private function refuseUnlessEstimable(Job $job): void
    {
        if (! $job->isWorking()) {
            throw new EstimateNotCreatable($job, EstimateNotCreatable::NOT_WORKING);
        }

        if ($job->isLocked()) {
            throw new EstimateNotCreatable($job, EstimateNotCreatable::LOCKED);
        }

        if (! $job->hasLineItems()) {
            throw new EstimateNotCreatable($job, EstimateNotCreatable::NO_LINE_ITEMS);
        }
    }

    private function supersedeOpenEstimate(Job $job): void
    {
        $openId = Estimate::query()
            ->where('job_id', $job->getKey())
            ->whereIn('status', EstimateStatus::open())
            ->value('id');

        if ($openId === null) {
            return;
        }

        $open = Estimate::query()->whereKey($openId)->lockForUpdate()->firstOrFail();

        if (! $open->status->isOpen()) {
            return;
        }

        $open->status = EstimateStatus::Superseded;
        $open->superseded_at = now();
        $open->save();
    }

    /**
     * @param  Closure(): Estimate  $creating
     */
    private function persist(Closure $creating): Estimate
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($creating);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }
}
