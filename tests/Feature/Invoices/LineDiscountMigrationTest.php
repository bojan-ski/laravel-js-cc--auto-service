<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DDL commits implicitly on MySQL, so this class cannot share the
 * LazilyRefreshDatabase transaction; see InvoiceBuyerBackfillTest.
 */
class LineDiscountMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(string $name): Migration
    {
        $paths = glob(database_path("migrations/*_{$name}.php"));
        $this->assertCount(1, $paths);

        return require $paths[0];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function migrations(): array
    {
        return [
            'job_items.discount_percent' => ['alter_job_items_table_add_discount_percent', 'job_items', 'discount_percent'],
            'invoices.discount_total' => ['alter_invoices_table_add_discount_total', 'invoices', 'discount_total'],
        ];
    }

    #[DataProvider('migrations')]
    public function test_rolling_back_restores_the_table_and_migrating_again_restores_the_column(string $name, string $table, string $column): void
    {
        $columnsAfter = Schema::getColumns($table);
        $indexesAfter = Schema::getIndexes($table);

        $this->migration($name)->down();

        $this->assertNotContains($column, Schema::getColumnListing($table));
        $this->assertEquals(
            collect($columnsAfter)->reject(fn (array $candidate): bool => $candidate['name'] === $column)->values()->all(),
            Schema::getColumns($table),
            "Rolling back must change nothing but {$column}.",
        );

        $this->migration($name)->up();

        $this->assertEquals($columnsAfter, Schema::getColumns($table));
        $this->assertEqualsCanonicalizing($indexesAfter, Schema::getIndexes($table));
    }

    public function test_rows_that_existed_before_the_migrations_read_zero(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $invoice = Invoice::factory()->create();

        $this->migration('alter_invoices_table_add_discount_total')->down();
        $this->migration('alter_job_items_table_add_discount_percent')->down();

        $this->migration('alter_job_items_table_add_discount_percent')->up();
        $this->migration('alter_invoices_table_add_discount_total')->up();

        $this->assertSame('0.00', DB::table('job_items')->where('id', $item->getKey())->value('discount_percent'));
        $this->assertSame('0.00', DB::table('invoices')->where('id', $invoice->getKey())->value('discount_total'));
    }
}
