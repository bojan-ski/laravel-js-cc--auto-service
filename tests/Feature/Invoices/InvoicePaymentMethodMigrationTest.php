<?php

namespace Tests\Feature\Invoices;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DDL commits implicitly on MySQL, so this class cannot share the
 * LazilyRefreshDatabase transaction; see InvoiceBuyerBackfillTest.
 */
class InvoicePaymentMethodMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(): Migration
    {
        $paths = glob(database_path('migrations/*_alter_invoices_table_add_payment_method.php'));
        $this->assertCount(1, $paths);

        return require $paths[0];
    }

    public function test_rolling_back_restores_the_table_and_migrating_again_restores_the_column(): void
    {
        $columnsAfter = Schema::getColumns('invoices');
        $indexesAfter = Schema::getIndexes('invoices');

        $this->migration()->down();

        $this->assertNotContains('payment_method', Schema::getColumnListing('invoices'));
        $this->assertFalse(
            collect(Schema::getIndexes('invoices'))->contains(fn (array $index): bool => in_array('payment_method', $index['columns'], true)),
            'The index must be dropped with the column.',
        );
        $this->assertEquals(
            collect($columnsAfter)->reject(fn (array $column): bool => $column['name'] === 'payment_method')->values()->all(),
            Schema::getColumns('invoices'),
            'Rolling back must change nothing but the payment method.',
        );

        $this->migration()->up();

        $this->assertEquals($columnsAfter, Schema::getColumns('invoices'));
        $this->assertEqualsCanonicalizing($indexesAfter, Schema::getIndexes('invoices'));
    }
}
