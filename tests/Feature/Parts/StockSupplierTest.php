<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Exceptions\InvalidStockMovement;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class StockSupplierTest extends TestCase
{
    use AssertsStockInvariant, LazilyRefreshDatabase;

    public function test_the_suppliers_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'name_normalized', 'created_at', 'updated_at'],
            Schema::getColumnListing('suppliers'),
        );
    }

    public function test_a_restock_records_its_supplier(): void
    {
        $part = Part::factory()->create();
        $supplier = Supplier::factory()->named('Bosch Srbija')->create();

        $movement = app(StockService::class)->record(
            $part,
            8,
            StockMovementReason::Restock,
            User::factory()->receptionist()->create(),
            supplier: $supplier,
        );

        $this->assertTrue($movement->fresh()->supplier->is($supplier));
        $this->assertStockMatchesLedger();
    }

    public function test_a_movement_without_a_supplier_stores_null(): void
    {
        $movement = app(StockService::class)->record(
            Part::factory()->create(),
            5,
            StockMovementReason::Restock,
            User::factory()->receptionist()->create(),
        );

        $this->assertNull($movement->fresh()->supplier_id);
    }

    /**
     * @return array<string, array{0: StockMovementReason, 1: int}>
     */
    public static function jobReasonProvider(): array
    {
        return [
            'job use' => [StockMovementReason::JobUse, -1],
            'job return' => [StockMovementReason::JobReturn, 1],
        ];
    }

    #[DataProvider('jobReasonProvider')]
    public function test_a_supplier_is_refused_on_a_job_driven_movement(
        StockMovementReason $reason,
        int $change,
    ): void {
        $part = Part::factory()->create();

        $this->expectException(InvalidStockMovement::class);

        app(StockService::class)->record(
            $part,
            $change,
            $reason,
            User::factory()->receptionist()->create(),
            supplier: Supplier::factory()->create(),
        );
    }

    #[DataProvider('jobReasonProvider')]
    public function test_the_refusal_moves_no_stock(
        StockMovementReason $reason,
        int $change,
    ): void {
        $part = Part::factory()->inStock(10)->create();

        try {
            app(StockService::class)->record(
                $part,
                $change,
                $reason,
                User::factory()->receptionist()->create(),
                supplier: Supplier::factory()->create(),
            );
        } catch (InvalidStockMovement) {
            // asserted below
        }

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertSame(0, $part->stockMovements()->count());
    }

    public function test_a_manual_adjustment_may_carry_a_supplier(): void
    {
        $movement = app(StockService::class)->record(
            Part::factory()->create(),
            -3,
            StockMovementReason::ManualAdjustment,
            User::factory()->receptionist()->create(),
            note: 'count correction',
            supplier: Supplier::factory()->create(),
        );

        $this->assertNotNull($movement->fresh()->supplier_id);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function equivalentNameProvider(): array
    {
        return [
            'same' => ['Bosch Srbija'],
            'upper case' => ['BOSCH SRBIJA'],
            'lower case' => ['bosch srbija'],
            'spacing' => ['BoschSrbija'],
            'padded' => ['  Bosch  Srbija  '],
        ];
    }

    #[DataProvider('equivalentNameProvider')]
    public function test_resolving_a_name_reuses_the_existing_supplier(string $name): void
    {
        $existing = Supplier::factory()->named('Bosch Srbija')->create();

        $resolved = app(SupplierService::class)->resolveByName($name);

        $this->assertTrue($resolved->is($existing));
        $this->assertSame(1, Supplier::query()->count());
    }

    public function test_resolving_an_unknown_name_creates_one_keeping_the_typed_form(): void
    {
        $resolved = app(SupplierService::class)->resolveByName('  Febi Bilstein  ');

        $this->assertSame('Febi Bilstein', $resolved->name);
        $this->assertSame('FEBIBILSTEIN', $resolved->name_normalized);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function blankNameProvider(): array
    {
        return ['null' => [null], 'empty' => [''], 'spaces' => ['   '], 'punctuation' => ['---']];
    }

    #[DataProvider('blankNameProvider')]
    public function test_a_blank_name_resolves_to_no_supplier(?string $name): void
    {
        $this->assertNull(app(SupplierService::class)->resolveByName($name));
        $this->assertSame(0, Supplier::query()->count());
    }

    public function test_the_picker_lists_known_suppliers_alphabetically(): void
    {
        Supplier::factory()->named('Zenith')->create();
        Supplier::factory()->named('Ate')->create();

        $this->assertSame(['Ate', 'Zenith'], app(SupplierService::class)->pickerOptions());
    }
}
