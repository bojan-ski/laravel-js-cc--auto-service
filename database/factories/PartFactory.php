<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * @var array<string, array<int, string>>
     */
    private const CATALOGUE = [
        'OF' => ['Oil filter', 'Air filter', 'Cabin filter', 'Fuel filter'],
        'BP' => ['Brake pads front', 'Brake pads rear', 'Brake disc front'],
        'WB' => ['Wiper blade 24"', 'Wiper blade 20"'],
        'SP' => ['Spark plug', 'Glow plug', 'Timing belt kit'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->randomElement(array_keys(self::CATALOGUE));

        return [
            'sku' => $prefix.'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->randomElement(self::CATALOGUE[$prefix]),
            'description' => fake()->sentence(),
            'unit_price' => fake()->randomFloat(2, 200, 9000),
            'cost_price' => fake()->randomFloat(2, 100, 6000),
            'quantity_in_stock' => 0,
            'reorder_level' => 0,
            'location' => strtoupper(fake()->bothify('?#')),
        ];
    }

    public function minimal(): static
    {
        return $this->state(fn (): array => [
            'description' => null,
            'cost_price' => null,
            'location' => null,
        ]);
    }

    public function inStock(int $quantity = 12): static
    {
        return $this->state(fn (): array => [
            'quantity_in_stock' => $quantity,
            'reorder_level' => 4,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (): array => [
            'quantity_in_stock' => 1,
            'reorder_level' => 4,
        ]);
    }

    public function negativeStock(): static
    {
        return $this->state(fn (): array => [
            'quantity_in_stock' => -2,
            'reorder_level' => 4,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
