<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Exceptions\InvalidJobTransition;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class JobService
{
    public const int REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly JobReferenceGenerator $references,
        private readonly AppointmentService $appointments,
    ) {}

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
        ]);
    }

    public function cancel(Job $job): Job
    {
        return $this->transition($job, 'cancel', fn (Job $job): bool => $job->canCancel(), JobStatus::Cancelled, [
            'cancelled_at' => now(),
        ]);
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
     */
    private function transition(Job $job, string $name, Closure $guard, JobStatus $status, array $changes): Job
    {
        if (! $guard($job)) {
            throw new InvalidJobTransition($job, $name);
        }

        DB::transaction(function () use ($job, $name, $guard, $status, $changes): void {
            $locked = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            if (! $guard($locked)) {
                throw new InvalidJobTransition($locked, $name);
            }

            $locked->status = $status;

            foreach ($changes as $column => $value) {
                $locked->{$column} = $value;
            }

            $locked->save();

            $job->setRawAttributes($locked->getAttributes(), true);
        });

        return $job;
    }
}
