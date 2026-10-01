<?php

namespace Database\Factories;

use App\Enums\ReminderKind;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleReminder>
 */
class VehicleReminderFactory extends Factory
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
            'customer_id' => fn (array $attributes): int => Vehicle::withTrashed()->findOrFail($attributes['vehicle_id'])->customer_id,
            'kind' => ReminderKind::Service,
            'due_on' => now()->addDays(7)->toDateString(),
            'sent_at' => now(),
        ];
    }

    public function forVehicle(Vehicle $vehicle, ReminderKind $kind = ReminderKind::Service): static
    {
        return $this->state(fn (): array => [
            'vehicle_id' => $vehicle->getKey(),
            'customer_id' => $vehicle->customer_id,
            'kind' => $kind,
            'due_on' => $vehicle->{$kind->dateColumn()}?->toDateString(),
        ]);
    }
}
