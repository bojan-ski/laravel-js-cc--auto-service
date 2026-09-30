<?php

namespace Database\Factories;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Job;
use App\Models\User;
use App\Services\EstimateNumberGenerator;
use App\Services\InvoiceService;
use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    private static int $sequenceFloor = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = number_format(fake()->numberBetween(1_000, 100_000), 2, '.', '');
        $rate = (string) config('shop.vat_rate');
        $tax = Money::round(bcdiv(bcmul($subtotal, $rate, 4), '100', 4));

        return [
            'number' => function (): string {
                $generated = app(EstimateNumberGenerator::class)->generate(now());
                $digits = EstimateNumberGenerator::SEQUENCE_DIGITS;
                $sequence = max((int) substr($generated, -$digits), self::$sequenceFloor + 1);
                self::$sequenceFloor = $sequence;

                return substr($generated, 0, -$digits).str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
            },
            'job_id' => Job::factory()->inProgress(),
            'status' => EstimateStatus::Pending,
            'subtotal' => $subtotal,
            'discount_total' => '0.00',
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'total' => bcadd($subtotal, $tax, 2),
            'decision_method' => null,
            'decided_at' => null,
            'decided_by' => null,
            'decision_note' => null,
            'superseded_at' => null,
            'emailed_at' => null,
            'created_by' => User::factory()->receptionist(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => EstimateStatus::Pending]);
    }

    public function approved(DecisionMethod $method = DecisionMethod::Phone): static
    {
        return $this->decided(EstimateStatus::Approved, $method);
    }

    public function declined(DecisionMethod $method = DecisionMethod::Phone): static
    {
        return $this->decided(EstimateStatus::Declined, $method);
    }

    public function superseded(): static
    {
        return $this->state(fn () => [
            'status' => EstimateStatus::Superseded,
            'superseded_at' => now()->subMinutes(5),
        ]);
    }

    public function emailed(): static
    {
        return $this->state(fn () => ['emailed_at' => now()->subMinutes(20)]);
    }

    public function forJob(Job $job): static
    {
        return $this->state(fn () => ['job_id' => $job->getKey()]);
    }

    public function withLines(): static
    {
        return $this
            ->state(fn () => [
                'subtotal' => fn (array $attributes): string => $this->totalsFrom($attributes)['subtotal'],
                'discount_total' => fn (array $attributes): string => $this->totalsFrom($attributes)['discount'],
                'tax_rate' => fn (array $attributes): string => $this->totalsFrom($attributes)['rate'],
                'tax_amount' => fn (array $attributes): string => $this->totalsFrom($attributes)['tax'],
                'total' => fn (array $attributes): string => $this->totalsFrom($attributes)['total'],
            ])
            ->afterCreating(function (Estimate $estimate): void {
                $position = 0;

                foreach ($this->pricedJob($estimate->job_id)->items as $item) {
                    EstimateLine::factory()->copying($item, ++$position)->for($estimate)->create();
                }
            });
    }

    private function decided(EstimateStatus $status, DecisionMethod $method): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'decision_method' => $method,
            'decided_at' => now()->subMinutes(10),
            'decided_by' => User::factory()->receptionist(),
        ]);
    }

    private function pricedJob(int $jobId): Job
    {
        return Job::query()->with(['items' => fn ($query) => $query->orderBy('id')])->findOrFail($jobId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    private function totalsFrom(array $attributes): array
    {
        return app(InvoiceService::class)->totalsFor($this->pricedJob($attributes['job_id']));
    }
}
