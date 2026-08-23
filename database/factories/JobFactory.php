<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    private static int $sequenceFloor = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => function (): string {
                $generated = app(JobReferenceGenerator::class)->generate(now());
                $digits = JobReferenceGenerator::SEQUENCE_DIGITS;
                $sequence = max((int) substr($generated, -$digits), self::$sequenceFloor + 1);
                self::$sequenceFloor = $sequence;

                return substr($generated, 0, -$digits).str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
            },
            'appointment_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'customer_id' => fn (array $attributes) => Vehicle::withTrashed()
                ->whereKey($attributes['vehicle_id'])
                ->value('customer_id'),
            'status' => JobStatus::Received,
            'complaint' => fake()->sentence(),
            'mileage_in' => fake()->numberBetween(1_000, 300_000),
            'started_at' => null,
            'awaiting_parts_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'locked_at' => null,
            'created_by' => User::factory()->receptionist(),
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => JobStatus::Received,
            'started_at' => null,
            'awaiting_parts_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => JobStatus::InProgress,
            'started_at' => now()->subHour(),
            'awaiting_parts_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function awaitingParts(): static
    {
        return $this->state(fn () => [
            'status' => JobStatus::AwaitingParts,
            'started_at' => now()->subHours(2),
            'awaiting_parts_at' => now()->subHour(),
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => JobStatus::Completed,
            'started_at' => now()->subHours(3),
            'awaiting_parts_at' => null,
            'completed_at' => now()->subHour(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => JobStatus::Cancelled,
            'started_at' => null,
            'awaiting_parts_at' => null,
            'completed_at' => null,
            'cancelled_at' => now()->subHour(),
        ]);
    }

    public function locked(): static
    {
        return $this->completed()->state(fn () => [
            'locked_at' => now()->subMinutes(30),
        ]);
    }

    public function walkIn(): static
    {
        return $this->state(fn () => ['appointment_id' => null]);
    }

    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn () => [
            'appointment_id' => $appointment->getKey(),
            'vehicle_id' => $appointment->vehicle_id,
            'customer_id' => $appointment->customer_id,
        ]);
    }
}
