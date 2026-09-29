<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
                $generated = app(InvoiceNumberGenerator::class)->generate(now());
                $digits = InvoiceNumberGenerator::SEQUENCE_DIGITS;
                $sequence = max((int) substr($generated, -$digits), self::$sequenceFloor + 1);
                self::$sequenceFloor = $sequence;

                return substr($generated, 0, -$digits).str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
            },
            'job_id' => Job::factory()->locked(),
            'buyer_name' => fn (array $attributes): string => $this->buyer($attributes)->name,
            'buyer_address' => fn (array $attributes): ?string => $this->buyer($attributes)->address,
            'buyer_tax_id' => fn (array $attributes): ?string => $this->buyer($attributes)->tax_id,
            'buyer_registration_number' => fn (array $attributes): ?string => $this->buyer($attributes)->registration_number,
            'status' => InvoiceStatus::Unpaid,
            'subtotal' => $subtotal,
            'discount_total' => '0.00',
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'total' => bcadd($subtotal, $tax, 2),
            'issued_at' => now()->subMinutes(30),
            'due_at' => null,
            'paid_at' => null,
            'payment_method' => null,
            'voided_at' => null,
            'voided_by' => null,
            'issued_by' => User::factory()->receptionist(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Unpaid,
            'paid_at' => null,
            'payment_method' => null,
            'voided_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now()->subMinutes(10),
            'payment_method' => PaymentMethod::Cash,
            'voided_at' => null,
        ]);
    }

    public function void(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Void,
            'voided_at' => now()->subMinutes(10),
            'voided_by' => User::factory()->admin(),
        ]);
    }

    public function forJob(Job $job): static
    {
        return $this->state(fn () => ['job_id' => $job->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function buyer(array $attributes): Customer
    {
        $customerId = Job::query()->whereKey($attributes['job_id'])->value('customer_id');

        return Customer::withTrashed()->findOrFail($customerId);
    }
}
