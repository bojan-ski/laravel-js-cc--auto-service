<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InvalidAppointmentTransition;
use App\Exceptions\InvalidJobTransition;
use App\Mail\JobCompletedMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class JobService
{
    public const int REFERENCE_ATTEMPTS = 3;

    public const int BOARD_PER_PAGE = 25;

    public const int HISTORY_LIMIT = 10;

    public const int ACTIVE_LIMIT = 10;

    public function __construct(
        private readonly JobReferenceGenerator $references,
        private readonly AppointmentService $appointments,
        private readonly StockService $stock,
        private readonly CustomerMailer $mailer,
    ) {}

    /**
     * @param  array{status: string, search: string, sort: string, direction: string}  $filters
     * @return LengthAwarePaginator<int, Job>
     */
    public function paginateFiltered(array $filters): LengthAwarePaginator
    {
        $direction = $filters['direction'];

        return $this->boardQuery($filters['search'])
            ->with(['vehicle', 'customer', 'laborItems:id,job_id,user_id,type', 'laborItems.user'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('jobs.status', $status))
            ->tap(fn (Builder $query) => match ($filters['sort']) {
                'plate' => $query->orderBy('vehicles.registration_plate', $direction),
                'customer' => $query->orderBy('customers.name', $direction),
                'status' => $query->orderByRaw(JobStatus::sortExpression().' '.$direction),
                default => $query->orderBy('jobs.created_at', $direction),
            })
            ->orderBy('jobs.id', $direction)
            ->paginate(self::BOARD_PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{status: string, search: string, sort: string, direction: string}  $filters
     * @return array<string, int>
     */
    public function countByStatus(array $filters): array
    {
        $counts = $this->boardQuery($filters['search'])
            ->select('jobs.status', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('jobs.status')
            ->pluck('aggregate', 'jobs.status');

        $result = [];

        foreach (JobStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    public function hasAny(): bool
    {
        return Job::query()->exists();
    }

    /**
     * @return Collection<int, Job>
     */
    public function active(): Collection
    {
        return $this->activeQuery()
            ->with('vehicle')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::ACTIVE_LIMIT)
            ->get();
    }

    public function countActive(): int
    {
        return $this->activeQuery()->count();
    }

    /**
     * @return Builder<Job>
     */
    private function activeQuery(): Builder
    {
        return Job::query()->whereIn('status', JobStatus::working());
    }

    /**
     * @return Collection<int, Job>
     */
    public function historyForCustomer(Customer $customer): Collection
    {
        return $customer->jobs()
            ->with('vehicle')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, Job>
     */
    public function historyForVehicle(Vehicle $vehicle): Collection
    {
        return $vehicle->jobs()
            ->with('customer')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    public function countForCustomer(Customer $customer): int
    {
        return $customer->jobs()->count();
    }

    public function countForVehicle(Vehicle $vehicle): int
    {
        return $vehicle->jobs()->count();
    }

    /**
     * @return Builder<Job>
     */
    private function boardQuery(string $search): Builder
    {
        return Job::query()
            ->joinedForBoard()
            ->when($search, fn (Builder $query, string $term) => $query->matching($term));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createWalkIn(Vehicle $vehicle, User $openedBy, array $attributes): Job
    {
        return $this->persist(function () use ($vehicle, $openedBy, $attributes): Job {
            return $this->insert($vehicle->customer_id, $vehicle, null, $openedBy, $attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromAppointment(Appointment $appointment, User $openedBy, array $attributes): Job
    {
        if (! $appointment->canBeMarkedArrived()) {
            throw new InvalidAppointmentTransition($appointment, 'mark arrived');
        }

        $vehicle = $appointment->vehicle()->firstOrFail();

        return $this->persist(function () use ($appointment, $vehicle, $openedBy, $attributes): Job {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            $this->appointments->markArrived($locked);

            $job = $this->insert($locked->customer_id, $vehicle, $locked, $openedBy, $attributes);

            $appointment->setRawAttributes($locked->getAttributes(), true);

            return $job;
        });
    }

    /**
     * @param  Closure(): Job  $creation
     */
    private function persist(Closure $creation): Job
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($creation);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insert(int $customerId, Vehicle $vehicle, ?Appointment $appointment, User $openedBy, array $attributes): Job
    {
        $job = new Job;
        $job->fill($attributes);

        $job->reference = $this->references->generate(now());
        $job->appointment_id = $appointment?->getKey();
        $job->customer_id = $customerId;
        $job->vehicle_id = $vehicle->getKey();
        $job->status = JobStatus::Received;
        $job->created_by = $openedBy->getKey();

        $job->save();

        $this->recordMileage($vehicle, $job);
        $job->setRelation('vehicle', $vehicle);

        return $job;
    }

    private function recordMileage(Vehicle $vehicle, Job $job): void
    {
        if ($job->mileage_in === null) {
            return;
        }

        $current = Vehicle::withTrashed()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();

        if ($current->mileage === null || $job->mileage_in > $current->mileage) {
            $current->mileage = $job->mileage_in;
            $current->save();
        }

        $vehicle->setRawAttributes($current->getAttributes(), true);
    }

    public function start(Job $job): Job
    {
        return $this->transition($job, 'start', fn (Job $job): bool => $job->canStart(), JobStatus::InProgress, [
            'started_at' => now(),
        ]);
    }

    public function pause(Job $job): Job
    {
        return $this->transition($job, 'pause', fn (Job $job): bool => $job->canPause(), JobStatus::AwaitingParts, [
            'awaiting_parts_at' => now(),
        ]);
    }

    public function resume(Job $job): Job
    {
        return $this->transition($job, 'resume', fn (Job $job): bool => $job->canResume(), JobStatus::InProgress, [
            'awaiting_parts_at' => null,
        ]);
    }

    public function complete(Job $job): Job
    {
        return $this->transition($job, 'complete', fn (Job $job): bool => $job->canComplete(), JobStatus::Completed, [
            'completed_at' => now(),
        ], fn (Job $locked) => $this->announceCompletion($locked));
    }

    private function announceCompletion(Job $job): void
    {
        $customer = Customer::withTrashed()->findOrFail($job->customer_id);
        $vehicle = Vehicle::withTrashed()->findOrFail($job->vehicle_id);

        $this->mailer->send($customer, new JobCompletedMail($job, $customer, $vehicle));
    }

    public function cancel(Job $job, User $actor): Job
    {
        return $this->transition($job, 'cancel', fn (Job $job): bool => $job->canCancel(), JobStatus::Cancelled, [
            'cancelled_at' => now(),
        ], fn (Job $locked) => $this->returnPartsToStock($locked, $actor));
    }

    public function reopen(Job $job): Job
    {
        return $this->transition($job, 'reopen', fn (Job $job): bool => $job->canReopen(), JobStatus::InProgress, [
            'completed_at' => null,
        ]);
    }

    /**
     * @param  Closure(Job): bool  $guard
     * @param  array<string, mixed>  $changes
     * @param  (Closure(Job): void)|null  $inside
     */
    private function transition(Job $job, string $name, Closure $guard, JobStatus $status, array $changes, ?Closure $inside = null): Job
    {
        if (! $guard($job)) {
            throw new InvalidJobTransition($job, $name);
        }

        DB::transaction(function () use ($job, $name, $guard, $status, $changes, $inside): void {
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            if (! $guard($locked)) {
                throw new InvalidJobTransition($locked, $name);
            }

            $locked->status = $status;

            foreach ($changes as $column => $value) {
                $locked->{$column} = $value;
            }

            $locked->save();

            if ($inside !== null) {
                $inside($locked);
            }

            $job->setRawAttributes($locked->getAttributes(), true);
        });

        return $job;
    }

    private function returnPartsToStock(Job $job, User $actor): void
    {
        foreach ($job->partItems()->with('part')->get() as $item) {
            $this->stock->record(
                $item->part,
                (int) $item->quantity,
                StockMovementReason::JobReturn,
                $actor,
                $item,
            );
        }
    }
}
