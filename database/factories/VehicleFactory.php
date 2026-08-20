<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @var array<string, array<int, string>>
     */
    private const MODELS = [
        'Volkswagen' => ['Golf IV', 'Golf V', 'Passat B6', 'Polo'],
        'Opel' => ['Astra H', 'Corsa D', 'Insignia'],
        'Ford' => ['Focus', 'Fiesta', 'Mondeo'],
        'Škoda' => ['Octavia II', 'Fabia', 'Superb'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $make = fake()->randomElement(array_keys(self::MODELS));

        return [
            'customer_id' => Customer::factory(),
            'registration_plate' => strtoupper(fake()->unique()->bothify('??###-??')),
            'make' => $make,
            'model' => fake()->randomElement(self::MODELS[$make]),
            'year' => fake()->numberBetween(1998, 2024),
            'vin' => strtoupper(fake()->unique()->bothify('???????????######')),
            'engine' => fake()->randomElement(['1.4 16V', '1.6 TDI', '1.9 TDI', '2.0 TDI', '1.2 TSI']),
            'mileage' => fake()->numberBetween(30_000, 320_000),
            'notes' => fake()->sentence(),
        ];
    }

    public function minimal(): static
    {
        return $this->state(fn () => [
            'year' => null,
            'vin' => null,
            'engine' => null,
            'mileage' => null,
            'notes' => null,
        ]);
    }
}
