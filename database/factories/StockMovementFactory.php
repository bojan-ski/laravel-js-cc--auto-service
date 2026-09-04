<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rows made here are written straight to the table and never pass through
 * StockService, so they do NOT move `parts.quantity_in_stock`. Use the factory
 * for rendering and relation fixtures; build anything that asserts the
 * stock-equals-ledger invariant through StockService::record() instead.
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part_id' => Part::factory(),
            'job_item_id' => null,
            'user_id' => User::factory()->receptionist(),
            'supplier_id' => null,
            'quantity_change' => fake()->numberBetween(1, 20),
            'reason' => StockMovementReason::Restock,
            'note' => null,
        ];
    }

    public function fromSupplier(?Supplier $supplier = null): static
    {
        return $this->state(fn (): array => [
            'supplier_id' => $supplier?->getKey() ?? Supplier::factory(),
        ]);
    }

    public function forPart(Part $part): static
    {
        return $this->state(fn (): array => ['part_id' => $part->getKey()]);
    }

    public function causedBy(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }

    public function forJobItem(JobItem $item): static
    {
        return $this->state(fn (): array => ['job_item_id' => $item->getKey()]);
    }

    public function restock(int $quantity = 12): static
    {
        return $this->state(fn (): array => [
            'reason' => StockMovementReason::Restock,
            'quantity_change' => abs($quantity),
        ]);
    }

    public function manualAdjustment(int $quantity = -3): static
    {
        return $this->state(fn (): array => [
            'reason' => StockMovementReason::ManualAdjustment,
            'quantity_change' => $quantity,
            'note' => 'count correction',
        ]);
    }

    public function jobUse(int $quantity = 1): static
    {
        return $this->state(fn (): array => [
            'reason' => StockMovementReason::JobUse,
            'quantity_change' => -abs($quantity),
        ]);
    }

    public function jobReturn(int $quantity = 1): static
    {
        return $this->state(fn (): array => [
            'reason' => StockMovementReason::JobReturn,
            'quantity_change' => abs($quantity),
        ]);
    }
}
