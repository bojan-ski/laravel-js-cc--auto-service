<?php

namespace Tests\Feature\Parts;

use App\Exceptions\PartImportFailed;
use App\Exceptions\UnreadableImport;
use App\Models\Brand;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PartImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class PartImportValidationTest extends TestCase
{
    use CreatesRealUploads, LazilyRefreshDatabase;

    /**
     * @return list<string>
     */
    private function errorsFor(string $csv, ?User $actor = null): array
    {
        try {
            app(PartImportService::class)->import(
                $this->realUpload('parts.csv', $csv),
                $actor ?? User::factory()->receptionist()->create(),
            );
        } catch (PartImportFailed $failure) {
            return $failure->errors();
        }

        $this->fail('The import was accepted but should have been rejected.');
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame(0, Part::query()->withTrashed()->count(), 'A part was written.');
        $this->assertSame(0, StockMovement::query()->count(), 'A movement was written.');
        $this->assertSame(0, PartNumber::query()->count(), 'An identifier was written.');
    }

    public function test_a_missing_required_column_is_named(): void
    {
        $errors = $this->errorsFor("sku,name\nBP-1,Pads\n");

        $this->assertContains(__('parts.import.errors.missing_column', ['column' => 'quantity']), $errors);
    }

    public function test_an_unrecognised_column_is_named(): void
    {
        $errors = $this->errorsFor("sku,quantity,colour\nBP-1,4,red\n");

        $this->assertContains(__('parts.import.errors.unknown_column', ['column' => 'colour']), $errors);
    }

    public function test_a_repeated_column_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity,quantity\nBP-1,4,5\n");

        $this->assertContains(__('parts.import.errors.duplicate_column'), $errors);
    }

    public function test_a_header_only_file_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity\n");

        $this->assertContains(__('parts.import.errors.no_rows'), $errors);
    }

    public function test_a_file_over_the_row_cap_is_rejected_before_anything_is_written(): void
    {
        config(['imports.max_rows' => 3]);

        $rows = '';

        foreach (range(1, 4) as $index) {
            $rows .= "BP-{$index},1,Part {$index},100.00\n";
        }

        $errors = $this->errorsFor("sku,quantity,name,unit_price\n".$rows);

        $this->assertContains(
            __('parts.import.errors.too_many_rows', ['count' => 4, 'max' => 3]),
            $errors,
        );
        $this->assertNothingWritten();
    }

    public function test_a_row_at_the_cap_is_accepted(): void
    {
        config(['imports.max_rows' => 2]);

        app(PartImportService::class)->import(
            $this->realUpload('parts.csv', "sku,quantity,name,unit_price\nBP-1,1,A,1.00\nBP-2,1,B,1.00\n"),
            User::factory()->receptionist()->create(),
        );

        $this->assertSame(2, Part::query()->count());
    }

    public function test_a_duplicate_sku_names_both_lines(): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price\nBP-1,4,A,1.00\nBP-2,4,B,1.00\nBP-1,9,C,1.00\n");

        $this->assertContains(
            __('parts.import.errors.duplicate_sku', ['sku' => 'BP-1', 'first' => 2, 'line' => 4]),
            $errors,
        );
        $this->assertNothingWritten();
    }

    public function test_a_row_without_a_sku_is_rejected_with_its_line(): void
    {
        $errors = $this->errorsFor("sku,quantity\n,4\n");

        $this->assertContains(__('parts.import.errors.missing_sku', ['line' => 2]), $errors);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function badQuantityProvider(): array
    {
        return [
            'text' => ['many'],
            'zero' => ['0'],
            'negative' => ['-3'],
            'decimal' => ['2.5'],
            'empty' => [''],
        ];
    }

    #[DataProvider('badQuantityProvider')]
    public function test_a_bad_quantity_is_rejected(string $quantity): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price\nBP-1,{$quantity},Pads,1.00\n");

        $this->assertContains(
            __('parts.import.errors.bad_quantity', ['line' => 2, 'sku' => 'BP-1']),
            $errors,
        );
    }

    public function test_a_new_part_without_a_name_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity,unit_price\nBP-1,4,100.00\n");

        $this->assertContains(
            __('parts.import.errors.missing_for_new_part', ['line' => 2, 'sku' => 'BP-1', 'column' => 'name']),
            $errors,
        );
    }

    public function test_a_new_part_without_a_price_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity,name\nBP-1,4,Pads\n");

        $this->assertContains(
            __('parts.import.errors.missing_for_new_part', ['line' => 2, 'sku' => 'BP-1', 'column' => 'unit_price']),
            $errors,
        );
    }

    /**
     * The same columns are optional for a row that only restocks, which is what
     * lets a delivery note carry two columns.
     */
    public function test_an_existing_part_needs_neither_name_nor_price(): void
    {
        Part::factory()->create(['sku' => 'BP-1']);

        app(PartImportService::class)->import(
            $this->realUpload('parts.csv', "sku,quantity\nBP-1,4\n"),
            User::factory()->receptionist()->create(),
        );

        $this->assertSame(4, Part::query()->where('sku', 'BP-1')->sole()->quantity_in_stock);
    }

    public function test_a_non_numeric_price_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price\nBP-1,4,Pads,free\n");

        $this->assertContains(__('parts.import.errors.bad_price', ['line' => 2, 'sku' => 'BP-1']), $errors);
    }

    public function test_a_non_numeric_reorder_level_is_rejected(): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price,reorder_level\nBP-1,4,Pads,1.00,lots\n");

        $this->assertContains(
            __('parts.import.errors.bad_reorder_level', ['line' => 2, 'sku' => 'BP-1']),
            $errors,
        );
    }

    public function test_an_unknown_brand_is_rejected_and_never_created(): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price,brand\nBP-1,4,Pads,1.00,Nonexistent\n");

        $this->assertContains(
            __('parts.import.errors.unknown_brand', ['line' => 2, 'brand' => 'Nonexistent']),
            $errors,
        );
        $this->assertSame(0, Brand::query()->withTrashed()->count());
        $this->assertNothingWritten();
    }

    public function test_a_trashed_brand_does_not_resolve(): void
    {
        Brand::factory()->trashed()->create(['name' => 'Bosch']);

        $errors = $this->errorsFor("sku,quantity,name,unit_price,brand\nBP-1,4,Pads,1.00,Bosch\n");

        $this->assertContains(
            __('parts.import.errors.unknown_brand', ['line' => 2, 'brand' => 'Bosch']),
            $errors,
        );
    }

    public function test_a_row_naming_a_deleted_part_is_rejected(): void
    {
        Part::factory()->deleted()->create(['sku' => 'BP-1']);

        $errors = $this->errorsFor("sku,quantity\nBP-1,4\n");

        $this->assertContains(
            __('parts.import.errors.deleted_part', ['line' => 2, 'sku' => 'BP-1']),
            $errors,
        );
    }

    public function test_a_receptionist_may_not_import_a_cost_price_column(): void
    {
        $errors = $this->errorsFor(
            "sku,quantity,name,unit_price,cost_price\nBP-1,4,Pads,1.00,600.00\n",
            User::factory()->receptionist()->create(),
        );

        $this->assertContains(__('parts.import.errors.cost_price_forbidden'), $errors);
        $this->assertNothingWritten();
    }

    public function test_an_admin_may(): void
    {
        app(PartImportService::class)->import(
            $this->realUpload('parts.csv', "sku,quantity,name,unit_price,cost_price\nBP-1,4,Pads,1.00,600.00\n"),
            User::factory()->admin()->create(),
        );

        $this->assertSame('600.00', Part::query()->sole()->cost_price);
    }

    public function test_every_bad_row_is_reported_not_just_the_first(): void
    {
        $errors = $this->errorsFor("sku,quantity,name,unit_price\nBP-1,nope,A,1.00\nBP-2,also-nope,B,1.00\n");

        $this->assertContains(__('parts.import.errors.bad_quantity', ['line' => 2, 'sku' => 'BP-1']), $errors);
        $this->assertContains(__('parts.import.errors.bad_quantity', ['line' => 3, 'sku' => 'BP-2']), $errors);
    }

    public function test_a_file_that_is_not_csv_at_all_is_unreadable(): void
    {
        $this->expectException(UnreadableImport::class);

        app(PartImportService::class)->import(
            $this->realUpload('parts.csv', ''),
            User::factory()->receptionist()->create(),
        );
    }
}
