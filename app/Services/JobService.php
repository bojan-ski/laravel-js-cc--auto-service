<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JobService
{
    public const int REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly JobReferenceGenerator $references,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Vehicle $vehicle, User $openedBy, array $attributes, ?Appointment $appointment = null): Job
    {
        if ($appointment !== null && $appointment->vehicle_id !== $vehicle->getKey()) {
            throw new InvalidArgumentException(
                "Appointment {$appointment->getKey()} is for vehicle {$appointment->vehicle_id}, not vehicle {$vehicle->getKey()}."
            );
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($vehicle, $openedBy, $attributes, $appointment): Job {
                    $job = new Job;
                    $job->fill($attributes);

                    $job->reference = $this->references->generate(now());
                    $job->appointment_id = $appointment?->getKey();
                    $job->customer_id = $vehicle->customer_id;
                    $job->vehicle_id = $vehicle->getKey();
                    $job->status = JobStatus::Received;
                    $job->created_by = $openedBy->getKey();

                    $job->save();

                    return $job;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
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
