<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Services\TaxIdChecksum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('06# ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'notes' => fake()->sentence(),
        ];
    }

    public function company(): static
    {
        return $this->state(function (): array {
            $prefix = fake()->numerify('########');

            return [
                'tax_id' => $prefix.TaxIdChecksum::checkDigit($prefix),
                'registration_number' => fake()->numerify('########'),
            ];
        });
    }

    public function minimal(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
    }
}
