<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Exceptions\InvalidStockMovement;
use App\Models\Part;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class StockMovementRejectionTest extends TestCase
{
    use AssertsStockInvariant, LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: StockMovementReason}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'zero restock' => [0, StockMovementReason::Restock],
            'zero adjustment' => [0, StockMovementReason::ManualAdjustment],
            'zero job use' => [0, StockMovementReason::JobUse],
            'negative restock' => [-5, StockMovementReason::Restock],
            'positive job use' => [5, StockMovementReason::JobUse],
            'negative job return' => [-5, StockMovementReason::JobReturn],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function test_the_service_refuses_the_movement(int $quantity, StockMovementReason $reason): void
    {
        $part = Part::factory()->create();

        try {
            app(StockService::class)->record($part, $quantity, $reason, User::factory()->receptionist()->create());
            $this->fail("A {$reason->value} movement of {$quantity} should have been refused.");
        } catch (InvalidStockMovement $exception) {
            $this->assertSame($part->getKey(), $exception->part->getKey());
            $this->assertSame($quantity, $exception->quantityChange);
            $this->assertSame($reason, $exception->reason);
        }
    }

    #[DataProvider('rejectedProvider')]
    public function test_a_refused_movement_leaves_both_the_quantity_and_the_ledger_untouched(
        int $quantity,
        StockMovementReason $reason,
    ): void {
        $part = Part::factory()->create();
        $user = User::factory()->receptionist()->create();

        app(StockService::class)->record($part, 8, StockMovementReason::Restock, $user);

        try {
            app(StockService::class)->record($part, $quantity, $reason, $user);
        } catch (InvalidStockMovement) {
            // expected
        }

        $this->assertSame(8, $part->fresh()->quantity_in_stock);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertStockMatchesLedger();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function adjustmentProvider(): array
    {
        return [
            'positive' => [4],
            'negative' => [-4],
        ];
    }

    #[DataProvider('adjustmentProvider')]
    public function test_a_manual_adjustment_is_accepted_with_either_sign(int $quantity): void
    {
        $part = Part::factory()->create();

        app(StockService::class)->record(
            $part,
            $quantity,
            StockMovementReason::ManualAdjustment,
            User::factory()->receptionist()->create(),
            note: 'count correction',
        );

        $this->assertSame($quantity, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    /**
     * @return array<string, array{0: StockMovementReason, 1: int, 2: bool}>
     */
    public static function reasonProvider(): array
    {
        return [
            'job use' => [StockMovementReason::JobUse, -1, false],
            'job return' => [StockMovementReason::JobReturn, 1, false],
            'restock' => [StockMovementReason::Restock, 1, true],
            'manual adjustment' => [StockMovementReason::ManualAdjustment, 0, true],
        ];
    }

    #[DataProvider('reasonProvider')]
    public function test_each_reason_declares_its_sign_and_whether_a_form_may_record_it(
        StockMovementReason $reason,
        int $sign,
        bool $byHand,
    ): void {
        $this->assertSame($sign, $reason->requiredSign());
        $this->assertSame($byHand, $reason->isRecordableByHand());
        $this->assertSame(__('parts.reasons.'.$reason->value), $reason->label());
        $this->assertNotSame('parts.reasons.'.$reason->value, $reason->label(), 'The reason label has no translation.');
    }

    public function test_only_the_two_hand_reasons_are_offered_to_a_form(): void
    {
        $this->assertSame(
            ['restock', 'manual_adjustment'],
            StockMovementReason::recordableByHandValues(),
        );
    }
}
