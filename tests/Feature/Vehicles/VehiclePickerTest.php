<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehiclePickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_options_are_keyed_by_id_labelled_with_plate_vehicle_and_owner_and_ordered_by_plate(): void
    {
        $owner = Customer::factory()->create(['name' => 'Mira Petrović']);
        $second = Vehicle::factory()->for($owner)->create(['registration_plate' => 'NS887BB', 'make' => 'Opel', 'model' => 'Astra H']);
        $first = Vehicle::factory()->for($owner)->create(['registration_plate' => 'BG123AA', 'make' => 'VW', 'model' => 'Golf IV']);

        $options = app(VehicleService::class)->pickerOptions();

        $this->assertSame([$first->getKey(), $second->getKey()], array_keys($options));
        $this->assertSame('BG123AA · VW Golf IV · Mira Petrović', $options[$first->getKey()]);
    }

    public function test_soft_deleted_vehicles_are_excluded(): void
    {
        $kept = Vehicle::factory()->create();
        $gone = Vehicle::factory()->create();
        $gone->delete();

        $options = app(VehicleService::class)->pickerOptions();

        $this->assertArrayHasKey($kept->getKey(), $options);
        $this->assertArrayNotHasKey($gone->getKey(), $options);
    }

    public function test_owners_are_eager_loaded(): void
    {
        Vehicle::factory()->count(5)->create();

        DB::enableQueryLog();
        app(VehicleService::class)->pickerOptions();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries);
    }
}
