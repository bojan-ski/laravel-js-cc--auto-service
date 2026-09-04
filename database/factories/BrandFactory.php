<?php

namespace Database\Factories;

use App\Enums\QualityTier;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'quality_tier' => fake()->randomElement(QualityTier::cases()),
        ];
    }

    public function aftermarket(): static
    {
        return $this->state(fn (): array => ['quality_tier' => QualityTier::Aftermarket]);
    }

    public function original(): static
    {
        return $this->state(fn (): array => ['quality_tier' => QualityTier::Original]);
    }

    public function firstFit(): static
    {
        return $this->state(fn (): array => ['quality_tier' => QualityTier::FirstFit]);
    }

    public function genuine(): static
    {
        return $this->state(fn (): array => ['quality_tier' => QualityTier::Genuine]);
    }

    public function trashed(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
