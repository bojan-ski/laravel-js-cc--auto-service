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

    /**
     * A customer can be soft-deleted while still owning vehicles, and the
     * picker feeds both the walk-in job form and the appointment form. Found
     * by spec 26's route sweep: the owner load omitted withTrashed(), so one
     * deleted customer turned both forms into a 500 for every receptionist.
     */
    public function test_a_vehicle_whose_owner_is_soft_deleted_is_still_offered_and_marked(): void
    {
        $owner = Customer::factory()->create(['name' => 'Mira Petrović']);
        $vehicle = Vehicle::factory()->for($owner)->create(['registration_plate' => 'BG123AA', 'make' => 'VW', 'model' => 'Golf IV']);
        $owner->delete();

        $options = app(VehicleService::class)->pickerOptions();

        $this->assertSame('BG123AA · VW Golf IV · Mira Petrović (deleted)', $options[$vehicle->getKey()]);
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
