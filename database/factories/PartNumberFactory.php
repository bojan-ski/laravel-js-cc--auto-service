<?php

namespace Database\Factories;

use App\Enums\PartNumberType;
use App\Models\Part;
use App\Models\PartNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartNumber>
 */
class PartNumberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part_id' => Part::factory(),
            'type' => PartNumberType::Oe,
            'number' => strtoupper(fake()->unique()->bothify('?#?##########')),
        ];
    }

    public function oe(): static
    {
        return $this->state(fn (): array => ['type' => PartNumberType::Oe]);
    }

    public function catalog(): static
    {
        return $this->state(fn (): array => ['type' => PartNumberType::Catalog]);
    }

    public function ean(): static
    {
        return $this->state(fn (): array => [
            'type' => PartNumberType::Ean,
            'number' => (string) fake()->unique()->numerify('#############'),
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn (): array => ['type' => PartNumberType::Supplier]);
    }

    public function number(string $number): static
    {
        return $this->state(fn (): array => ['number' => $number]);
    }
}
