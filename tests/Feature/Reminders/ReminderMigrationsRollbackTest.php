<?php

namespace Tests\Feature\Reminders;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DatabaseMigrations, not LazilyRefreshDatabase: MySQL commits DDL implicitly,
 * so a down()/up() pair inside the test transaction would escape it.
 */
class ReminderMigrationsRollbackTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(string $name): Migration
    {
        $paths = glob(database_path("migrations/*_{$name}.php"));
        $this->assertCount(1, $paths, "Expected exactly one {$name} migration.");

        return require $paths[0];
    }

    public function test_each_migration_rolls_back_exactly_what_it_added(): void
    {
        $vehiclesBefore = Schema::getColumnListing('vehicles');
        $customersBefore = Schema::getColumnListing('customers');

        $this->migration('create_vehicle_reminders_table')->down();
        $this->migration('alter_customers_table_add_receives_reminders')->down();
        $this->migration('alter_vehicles_table_add_service_dates')->down();

        $this->assertFalse(Schema::hasTable('vehicle_reminders'));
        $this->assertSame(array_values(array_diff($customersBefore, ['receives_reminders'])), Schema::getColumnListing('customers'));
        $this->assertSame(
            array_values(array_diff($vehiclesBefore, ['next_service_on', 'next_service_mileage', 'registration_expires_on'])),
            Schema::getColumnListing('vehicles'),
        );
        $this->assertEqualsCanonicalizing(
            ['primary', 'vehicles_customer_id_foreign', 'vehicles_registration_plate_unique', 'vehicles_vin_unique'],
            collect(Schema::getIndexes('vehicles'))->pluck('name')->all(),
        );

        $this->migration('alter_vehicles_table_add_service_dates')->up();
        $this->migration('alter_customers_table_add_receives_reminders')->up();
        $this->migration('create_vehicle_reminders_table')->up();

        $this->assertSame($vehiclesBefore, Schema::getColumnListing('vehicles'));
        $this->assertSame($customersBefore, Schema::getColumnListing('customers'));
        $this->assertTrue(Schema::hasTable('vehicle_reminders'));
    }
}
