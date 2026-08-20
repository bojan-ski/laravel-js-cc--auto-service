<?php

namespace Database\Factories;

use App\Models\Customer;
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

    public function minimal(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);
    }
}
