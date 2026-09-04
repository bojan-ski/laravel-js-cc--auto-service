<?php

namespace Tests\Feature\Parts;

use App\Exceptions\PartImportFailed;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PartImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

/**
 * All-or-nothing is the feature's headline promise. Two mechanisms deliver it
 * and they are tested separately, because only one of them is a transaction:
 *
 *  1. Validation runs over the WHOLE file before any write, so a rejected file
 *     never reaches the database at all. That is what these tests mostly pin,
 *     and it is real: no rollback is involved.
 *  2. The writes themselves run inside DB::transaction().
 *
 * REPORTED PLAINLY: `LazilyRefreshDatabase` wraps each test in its own
 * transaction, so the service's transaction is a SAVEPOINT. A rollback test
 * here proves the savepoint unwinds — not that a real COMMIT never happened.
 * Genuine crash-mid-commit behaviour is not covered by this suite, the same
 * limitation specs 15–17 recorded for concurrency.
 */
class PartImportAtomicityTest extends TestCase
{
    use CreatesRealUploads, LazilyRefreshDatabase;

    private function import(string $csv): void
    {
        app(PartImportService::class)->import(
            $this->realUpload('parts.csv', $csv),
            User::factory()->admin()->create(),
        );
    }

    private function assertDatabaseUntouched(): void
    {
        $this->assertSame(0, Part::query()->withTrashed()->count(), 'A part survived a rejected import.');
        $this->assertSame(0, StockMovement::query()->count(), 'A movement survived a rejected import.');
        $this->assertSame(0, PartNumber::query()->count(), 'An identifier survived a rejected import.');
        $this->assertSame(0, Supplier::query()->count(), 'A supplier survived a rejected import.');
    }

    /**
     * The failing row is LAST, so every earlier row is valid and would have
     * been written by a row-at-a-time importer.
     */
    public function test_a_failure_on_the_last_row_writes_nothing_from_the_earlier_rows(): void
    {
        try {
            $this->import(
                "sku,quantity,name,unit_price,oe_numbers,supplier\n".
                "BP-1,4,First,100.00,1K0615301AA,Febi\n".
                "BP-2,5,Second,200.00,8K0698451D,Febi\n".
                "BP-3,nope,Third,300.00,,Febi\n"
            );
            $this->fail('The import was accepted but should have been rejected.');
        } catch (PartImportFailed) {
            // asserted below
        }

        $this->assertDatabaseUntouched();
    }

    public function test_a_failure_on_the_first_row_also_writes_nothing(): void
    {
        try {
            $this->import(
                "sku,quantity,name,unit_price\n".
                "BP-1,nope,First,100.00\n".
                "BP-2,5,Second,200.00\n"
            );
            $this->fail('The import was accepted but should have been rejected.');
        } catch (PartImportFailed) {
            // asserted below
        }

        $this->assertDatabaseUntouched();
    }

    public function test_an_existing_parts_stock_is_untouched_when_a_later_row_fails(): void
    {
        $part = Part::factory()->create(['sku' => 'BP-1']);

        try {
            $this->import("sku,quantity\nBP-1,4\nBP-2,nope\n");
            $this->fail('The import was accepted but should have been rejected.');
        } catch (PartImportFailed) {
            // asserted below
        }

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
        $this->assertSame(0, $part->stockMovements()->count());
    }

    /**
     * A header problem is caught before rows are even examined, so this pins
     * the earliest of the two rejection points.
     */
    public function test_a_bad_header_row_writes_nothing(): void
    {
        try {
            $this->import("sku,name\nBP-1,Pads\n");
            $this->fail('The import was accepted but should have been rejected.');
        } catch (PartImportFailed) {
            // asserted below
        }

        $this->assertDatabaseUntouched();
    }

    /**
     * Suppliers are the one thing a row can create as a side effect, so they
     * are the likeliest to leak past a rejection.
     */
    public function test_no_supplier_is_created_when_a_later_row_fails(): void
    {
        try {
            $this->import(
                "sku,quantity,name,unit_price,supplier\n".
                "BP-1,4,First,100.00,Brand New Supplier\n".
                "BP-2,nope,Second,200.00,Brand New Supplier\n"
            );
            $this->fail('The import was accepted but should have been rejected.');
        } catch (PartImportFailed) {
            // asserted below
        }

        $this->assertSame(0, Supplier::query()->count());
    }

    public function test_a_valid_file_is_written_in_full(): void
    {
        $this->import(
            "sku,quantity,name,unit_price,oe_numbers,supplier\n".
            "BP-1,4,First,100.00,1K0615301AA,Febi\n".
            "BP-2,5,Second,200.00,8K0698451D,Febi\n"
        );

        $this->assertSame(2, Part::query()->count());
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame(2, PartNumber::query()->count());
        $this->assertSame(1, Supplier::query()->count());
    }
}
