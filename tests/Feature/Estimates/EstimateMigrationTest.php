<?php

namespace Tests\Feature\Estimates;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DDL commits implicitly on MySQL, so this class cannot share the
 * LazilyRefreshDatabase transaction; see InvoiceBuyerBackfillTest.
 */
class EstimateMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(string $name): Migration
    {
        $paths = glob(database_path("migrations/*_{$name}.php"));
        $this->assertCount(1, $paths);

        return require $paths[0];
    }

    public function test_rolling_back_drops_each_table_and_migrating_again_restores_it(): void
    {
        $lines = Schema::getColumns('estimate_lines');
        $estimates = Schema::getColumns('estimates');

        $this->migration('create_estimate_lines_table')->down();
        $this->assertFalse(Schema::hasTable('estimate_lines'));
        $this->assertTrue(Schema::hasTable('estimates'));

        $this->migration('create_estimates_table')->down();
        $this->assertFalse(Schema::hasTable('estimates'));

        $this->migration('create_estimates_table')->up();
        $this->migration('create_estimate_lines_table')->up();

        $this->assertEquals($estimates, Schema::getColumns('estimates'));
        $this->assertEquals($lines, Schema::getColumns('estimate_lines'));
    }
}
