<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Models\Brand;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PartImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsStockInvariant;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class PartImportServiceTest extends TestCase
{
    use AssertsStockInvariant, CreatesRealUploads, LazilyRefreshDatabase;

    /**
     * @return array{created: int, restocked: int, details_ignored: int}
     */
    private function import(string $csv, ?User $actor = null): array
    {
        return app(PartImportService::class)->import(
            $this->realUpload('parts.csv', $csv),
            $actor ?? User::factory()->receptionist()->create(),
        );
    }

    public function test_a_new_sku_creates_a_part_and_a_restock_movement(): void
    {
        $result = $this->import("sku,quantity,name,unit_price\nBP-9001,6,Brake pads front,4200.00\n");

        $part = Part::query()->where('sku', 'BP-9001')->sole();

        $this->assertSame(['created' => 1, 'restocked' => 0, 'details_ignored' => 0], $result);
        $this->assertSame('Brake pads front', $part->name);
        $this->assertSame(6, $part->quantity_in_stock);
        $this->assertSame(1, $part->stockMovements()->count());
        $this->assertSame(StockMovementReason::Restock, $part->stockMovements()->sole()->reason);
        $this->assertStockMatchesLedger();
    }

    /**
     * The part is created at zero and the quantity arrives as a movement, so
     * every unit on the shelf is explained by a ledger row.
     */
    public function test_the_created_parts_quantity_comes_entirely_from_the_movement(): void
    {
        $this->import("sku,quantity,name,unit_price\nBP-9001,6,Pads,4200.00\n");

        $part = Part::query()->where('sku', 'BP-9001')->sole();

        $this->assertSame(6, (int) $part->stockMovements()->sum('quantity_change'));
        $this->assertSame(6, $part->quantity_in_stock);
    }

    public function test_an_existing_sku_is_restocked(): void
    {
        $part = Part::factory()->inStock(10)->create(['sku' => 'BP-9001']);

        $result = $this->import("sku,quantity\nBP-9001,5\n");

        $this->assertSame(['created' => 0, 'restocked' => 1, 'details_ignored' => 0], $result);
        $this->assertSame(15, $part->fresh()->quantity_in_stock);
    }

    public function test_details_on_an_existing_parts_row_are_ignored_and_counted(): void
    {
        Brand::factory()->create(['name' => 'Bosch']);
        $part = Part::factory()->inStock(2)->create([
            'sku' => 'BP-9001',
            'name' => 'Original name',
            'unit_price' => 1000,
            'category' => 'Original category',
        ]);

        $result = $this->import(
            "sku,quantity,name,unit_price,brand,category\nBP-9001,3,New name,9999.00,Bosch,New category\n"
        );

        $fresh = $part->fresh();

        $this->assertSame(1, $result['details_ignored']);
        $this->assertSame('Original name', $fresh->name);
        $this->assertSame('1000.00', $fresh->unit_price);
        $this->assertSame('Original category', $fresh->category);
        $this->assertNull($fresh->brand_id);
        $this->assertSame(5, $fresh->quantity_in_stock);
    }

    public function test_a_bare_restock_row_is_not_counted_as_carrying_details(): void
    {
        Part::factory()->inStock(2)->create(['sku' => 'BP-9001']);

        $result = $this->import("sku,quantity\nBP-9001,3\n");

        $this->assertSame(0, $result['details_ignored']);
    }

    /**
     * Pins the reading taken during `start`: a row for an existing part does
     * exactly one thing, restock. Identifiers are a part detail, not part of
     * the delivery, so they are ignored like every other detail. Reversing
     * this decision should fail exactly this test.
     */
    public function test_oe_numbers_on_an_existing_parts_row_are_ignored(): void
    {
        $part = Part::factory()->inStock(2)->create(['sku' => 'BP-9001']);

        $this->import("sku,quantity,oe_numbers\nBP-9001,3,1K0615301AA\n");

        $this->assertSame(0, $part->fresh()->numbers()->count());
    }

    /**
     * The test above passes structurally — a row for an existing part never
     * reaches the code that attaches numbers — so it cannot see whether
     * `oe_numbers` is classified as a part detail. This one can: it is the
     * only column on the row, so the ignored-details count is 1 only while
     * that classification holds. Found by mutation.
     */
    public function test_an_oe_only_row_for_an_existing_part_counts_as_ignored_details(): void
    {
        Part::factory()->inStock(2)->create(['sku' => 'BP-9001']);

        $result = $this->import("sku,quantity,oe_numbers\nBP-9001,3,1K0615301AA\n");

        $this->assertSame(1, $result['details_ignored']);
    }

    /**
     * The counterpart: a supplier describes the delivery, not the part, so a
     * row carrying only a supplier is not "details that were ignored" — it was
     * fully honoured.
     */
    public function test_a_supplier_only_row_for_an_existing_part_counts_no_ignored_details(): void
    {
        Part::factory()->inStock(2)->create(['sku' => 'BP-9001']);

        $result = $this->import("sku,quantity,supplier\nBP-9001,3,Febi Bilstein\n");

        $this->assertSame(0, $result['details_ignored']);
    }

    public function test_a_new_part_takes_its_brand_by_name(): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);

        $this->import("sku,quantity,name,unit_price,brand\nBP-9001,4,Pads,4200.00,Bosch\n");

        $this->assertSame($brand->getKey(), Part::query()->where('sku', 'BP-9001')->sole()->brand_id);
    }

    public function test_a_brand_name_matches_regardless_of_case(): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);

        $this->import("sku,quantity,name,unit_price,brand\nBP-9001,4,Pads,4200.00,BOSCH\n");

        $this->assertSame($brand->getKey(), Part::query()->where('sku', 'BP-9001')->sole()->brand_id);
    }

    public function test_oe_numbers_are_split_on_the_pipe_and_normalized(): void
    {
        $this->import(
            "sku,quantity,name,unit_price,oe_numbers\nBP-9001,4,Pads,4200.00,1K0 615 301 AA|8K0-698-451-D\n"
        );

        $numbers = Part::query()->where('sku', 'BP-9001')->sole()->numbers;

        $this->assertSame(['1K0615301AA', '8K0698451D'], $numbers->pluck('number_normalized')->all());
        $this->assertSame('1K0 615 301 AA', $numbers->first()->number);
    }

    public function test_a_repeated_number_within_one_cell_is_stored_once(): void
    {
        $this->import(
            "sku,quantity,name,unit_price,oe_numbers\nBP-9001,4,Pads,4200.00,1K0615301AA|1k0-615-301-aa\n"
        );

        $this->assertSame(1, Part::query()->where('sku', 'BP-9001')->sole()->numbers()->count());
    }

    public function test_an_empty_oe_cell_creates_no_numbers(): void
    {
        $this->import("sku,quantity,name,unit_price,oe_numbers\nBP-9001,4,Pads,4200.00,\n");

        $this->assertSame(0, PartNumber::query()->count());
    }

    public function test_an_unknown_supplier_is_created_and_attached(): void
    {
        $this->import("sku,quantity,name,unit_price,supplier\nBP-9001,4,Pads,4200.00,Febi Bilstein\n");

        $supplier = Supplier::query()->sole();

        $this->assertSame('Febi Bilstein', $supplier->name);
        $this->assertSame($supplier->getKey(), StockMovement::query()->sole()->supplier_id);
    }

    public function test_a_known_supplier_is_reused_regardless_of_spacing_and_case(): void
    {
        $supplier = Supplier::factory()->named('Febi Bilstein')->create();

        $this->import("sku,quantity,name,unit_price,supplier\nBP-9001,4,Pads,4200.00,FEBIBILSTEIN\n");

        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame($supplier->getKey(), StockMovement::query()->sole()->supplier_id);
    }

    public function test_a_supplier_applies_to_an_existing_parts_restock_too(): void
    {
        Part::factory()->inStock(1)->create(['sku' => 'BP-9001']);

        $this->import("sku,quantity,supplier\nBP-9001,3,Febi Bilstein\n");

        $this->assertNotNull(StockMovement::query()->sole()->supplier_id);
    }

    public function test_the_movement_carries_an_import_note(): void
    {
        $this->import("sku,quantity,name,unit_price\nBP-9001,4,Pads,4200.00\n");

        $this->assertSame(__('parts.import.movement_note'), StockMovement::query()->sole()->note);
    }

    public function test_the_movement_is_attributed_to_the_importing_user(): void
    {
        $actor = User::factory()->admin()->create();

        $this->import("sku,quantity,name,unit_price\nBP-9001,4,Pads,4200.00\n", $actor);

        $this->assertSame($actor->getKey(), StockMovement::query()->sole()->user_id);
    }

    public function test_optional_columns_default_rather_than_storing_empty_strings(): void
    {
        $this->import("sku,quantity,name,unit_price,category,location,reorder_level\nBP-9001,4,Pads,4200.00,,,\n");

        $part = Part::query()->where('sku', 'BP-9001')->sole();

        $this->assertNull($part->category);
        $this->assertNull($part->location);
        $this->assertSame(0, $part->reorder_level);
    }

    public function test_optional_columns_are_stored_when_present(): void
    {
        $this->import(
            "sku,quantity,name,unit_price,category,location,reorder_level\nBP-9001,4,Pads,4200.00,Braking,A3,6\n"
        );

        $part = Part::query()->where('sku', 'BP-9001')->sole();

        $this->assertSame('Braking', $part->category);
        $this->assertSame('A3', $part->location);
        $this->assertSame(6, $part->reorder_level);
    }

    public function test_an_admin_may_import_a_cost_price(): void
    {
        $this->import(
            "sku,quantity,name,unit_price,cost_price\nBP-9001,4,Pads,4200.00,2600.00\n",
            User::factory()->admin()->create(),
        );

        $this->assertSame('2600.00', Part::query()->where('sku', 'BP-9001')->sole()->cost_price);
    }

    /**
     * The two existing parts are built at zero rather than with `inStock()`,
     * which writes a quantity and no movement: a fixture that already breaks
     * the stock-equals-ledger invariant cannot be used to assert it survives.
     */
    public function test_a_mixed_file_creates_restocks_and_counts_correctly(): void
    {
        Part::factory()->create(['sku' => 'BP-1']);
        Part::factory()->create(['sku' => 'BP-2']);

        $result = $this->import(
            "sku,quantity,name,unit_price\n".
            "BP-1,2,Ignored,1.00\n".
            "BP-2,3,\n".
            "BP-3,4,New part,4200.00\n"
        );

        $this->assertSame(['created' => 1, 'restocked' => 2, 'details_ignored' => 1], $result);
        $this->assertStockMatchesLedger();
    }

    /**
     * Lookups are batched: one select resolves every existing SKU, one the
     * brand, one the supplier. The remaining selects are StockService's
     * per-row `lockForUpdate` read, which the ledger requires and which no
     * batching may remove. Moving the brand or supplier lookup inside the loop
     * would double this count, which is exactly what this pins.
     */
    public function test_lookups_are_batched_rather_than_run_per_row(): void
    {
        Brand::factory()->create(['name' => 'Bosch']);

        foreach (range(1, 10) as $index) {
            Part::factory()->create(['sku' => "EX-{$index}"]);
        }

        $csv = "sku,quantity,name,unit_price,brand,supplier\n";

        foreach (range(1, 10) as $index) {
            $csv .= "EX-{$index},2,x,1.00,Bosch,Acme\n";
        }

        foreach (range(1, 10) as $index) {
            $csv .= "NEW-{$index},2,New {$index},100.00,Bosch,Acme\n";
        }

        DB::enableQueryLog();
        $this->import($csv);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $selects = array_filter(
            array_column($queries, 'query'),
            fn (string $query): bool => str_starts_with($query, 'select'),
        );

        // 20 locked reads + existing-parts + brand + supplier.
        $this->assertCount(23, $selects, implode("\n", $selects));
    }

    public function test_the_stock_invariant_holds_across_a_larger_file(): void
    {
        $rows = '';

        foreach (range(1, 40) as $index) {
            $rows .= "BULK-{$index},{$index},Part {$index},1000.00\n";
        }

        $this->import("sku,quantity,name,unit_price\n".$rows);

        $this->assertSame(40, Part::query()->count());
        $this->assertStockMatchesLedger();
    }
}
