<?php

namespace Database\Factories;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobItem>
 */
class JobItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory()->inProgress(),
            'type' => JobItemType::Labor,
            'part_id' => null,
            'user_id' => User::factory()->mechanic(),
            'description' => fake()->sentence(3),
            'quantity' => 1.50,
            'unit_price' => config('shop.labor_rate'),
        ];
    }

    public function forJob(Job $job): static
    {
        return $this->state(fn () => ['job_id' => $job->getKey()]);
    }

    public function performedBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->getKey()]);
    }

    public function part(): static
    {
        return $this->state(fn () => [
            'type' => JobItemType::Part,
            'user_id' => null,
        ]);
    }
}
