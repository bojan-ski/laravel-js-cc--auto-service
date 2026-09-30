<?php

namespace Database\Factories;

use App\Enums\JobItemType;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\JobItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateLine>
 */
class EstimateLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_id' => Estimate::factory(),
            'type' => JobItemType::Labor,
            'description' => fake()->sentence(3),
            'quantity' => '1.50',
            'unit_price' => (string) config('shop.labor_rate'),
            'discount_percent' => '0.00',
            'line_total' => fn (array $attributes): string => $this->lineTotal($attributes),
            'position' => 1,
        ];
    }

    public function copying(JobItem $item, int $position): static
    {
        return $this->state(fn () => [
            'type' => $item->type,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_percent' => $item->discount_percent,
            'line_total' => $item->lineTotal(),
            'position' => $position,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lineTotal(array $attributes): string
    {
        $item = new JobItem;
        $item->quantity = $attributes['quantity'];
        $item->unit_price = $attributes['unit_price'];
        $item->discount_percent = $attributes['discount_percent'];

        return $item->lineTotal();
    }
}
