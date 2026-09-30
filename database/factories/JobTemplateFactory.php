<?php

namespace Database\Factories;

use App\Models\JobTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTemplate>
 */
class JobTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => null,
        ];
    }

    public function trashed(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
