<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'customer_id' => fn (array $attributes) => Vehicle::withTrashed()
                ->whereKey($attributes['vehicle_id'])
                ->value('customer_id'),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+3 weeks'),
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'status' => AppointmentStatus::Booked,
            'arrived_at' => null,
            'no_show_at' => null,
            'cancelled_at' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory()->receptionist(),
        ];
    }

    public function booked(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Booked,
            'arrived_at' => null,
            'no_show_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function arrived(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Arrived,
            'arrived_at' => now(),
            'no_show_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::NoShow,
            'arrived_at' => null,
            'no_show_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Cancelled,
            'arrived_at' => null,
            'no_show_at' => null,
            'cancelled_at' => now(),
        ]);
    }
}
