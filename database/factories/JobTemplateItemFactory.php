<?php

namespace Database\Factories;

use App\Enums\JobItemType;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTemplateItem>
 */
class JobTemplateItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_template_id' => JobTemplate::factory(),
            'type' => JobItemType::Labor,
            'part_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => 1.50,
            'position' => fn (array $attributes): int => JobTemplateItem::query()
                ->where('job_template_id', $attributes['job_template_id'])
                ->max('position') + 1,
        ];
    }

    public function forTemplate(JobTemplate $template): static
    {
        return $this->state(fn (): array => ['job_template_id' => $template->getKey()]);
    }

    public function labor(): static
    {
        return $this->state(fn (): array => [
            'type' => JobItemType::Labor,
            'part_id' => null,
            'description' => fake()->sentence(3),
        ]);
    }

    public function forPart(Part $part, int $quantity = 1): static
    {
        return $this->state(fn (): array => [
            'type' => JobItemType::Part,
            'part_id' => $part->getKey(),
            'description' => null,
            'quantity' => $quantity,
        ]);
    }
}
