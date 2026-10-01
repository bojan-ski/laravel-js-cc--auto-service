<?php

namespace Tests\Feature\Reminders;

use App\Exceptions\VehicleReminderImmutable;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleReminderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_reminder_refuses_to_be_updated(): void
    {
        $reminder = VehicleReminder::factory()->create();
        $reminder->due_on = now()->addYear();

        $this->expectException(VehicleReminderImmutable::class);

        $reminder->save();
    }

    public function test_a_reminder_refuses_to_be_deleted(): void
    {
        $reminder = VehicleReminder::factory()->create();

        try {
            $reminder->delete();
            $this->fail('A reminder was deleted.');
        } catch (VehicleReminderImmutable) {
            $this->assertModelExists($reminder);
        }
    }

    public function test_nothing_is_mass_assignable(): void
    {
        $this->assertSame([], (new VehicleReminder)->getFillable());
        $this->assertFalse((new VehicleReminder)->isFillable('customer_id'));
    }

    public function test_the_vehicle_and_customer_resolve_when_both_are_trashed(): void
    {
        $reminder = VehicleReminder::factory()->create();

        Vehicle::query()->findOrFail($reminder->vehicle_id)->delete();
        Customer::query()->findOrFail($reminder->customer_id)->delete();

        $fresh = VehicleReminder::query()->findOrFail($reminder->getKey());

        $this->assertNotNull($fresh->vehicle);
        $this->assertNotNull($fresh->customer);
    }

    public function test_a_vehicle_lists_only_its_own_reminders(): void
    {
        $vehicle = Vehicle::factory()->create(['next_service_on' => now()->addDays(5)]);
        $mine = VehicleReminder::factory()->forVehicle($vehicle)->create();
        VehicleReminder::factory()->create();

        $this->assertSame([$mine->getKey()], $vehicle->reminders()->pluck('id')->all());
    }

    public function test_one_reminder_per_vehicle_kind_and_date(): void
    {
        $vehicle = Vehicle::factory()->create(['next_service_on' => now()->addDays(5)]);
        VehicleReminder::factory()->forVehicle($vehicle)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        VehicleReminder::factory()->forVehicle($vehicle)->create();
    }

    public function test_the_live_schema_matches_the_contract(): void
    {
        $columns = collect(Schema::getColumns('vehicle_reminders'))->keyBy('name');

        $this->assertSame(
            ['id', 'vehicle_id', 'customer_id', 'kind', 'due_on', 'sent_at', 'created_at', 'updated_at'],
            $columns->keys()->all(),
        );
        $this->assertSame('varchar(20)', $columns['kind']['type']);
        $this->assertSame('date', $columns['due_on']['type']);
        $this->assertFalse($columns['sent_at']['nullable']);

        $indexes = collect(Schema::getIndexes('vehicle_reminders'))->keyBy(fn (array $index): string => implode(',', $index['columns']));
        $this->assertTrue($indexes['vehicle_id,kind,due_on']['unique']);
        $this->assertArrayHasKey('kind', $indexes->all());

        $foreignKeys = collect(Schema::getForeignKeys('vehicle_reminders'))->keyBy(fn (array $key): string => $key['columns'][0]);
        $this->assertSame('restrict', $foreignKeys['vehicle_id']['on_delete']);
        $this->assertSame('restrict', $foreignKeys['customer_id']['on_delete']);

        $vehicles = collect(Schema::getColumns('vehicles'))->keyBy('name');
        $this->assertSame('date', $vehicles['next_service_on']['type']);
        $this->assertSame('int unsigned', $vehicles['next_service_mileage']['type']);
        $this->assertSame('date', $vehicles['registration_expires_on']['type']);

        $vehicleIndexes = collect(Schema::getIndexes('vehicles'))->map(fn (array $index): string => implode(',', $index['columns']));
        $this->assertContains('next_service_on', $vehicleIndexes->all());
        $this->assertContains('registration_expires_on', $vehicleIndexes->all());

        $customers = collect(Schema::getColumns('customers'))->keyBy('name');
        $this->assertSame('tinyint(1)', $customers['receives_reminders']['type']);
        $this->assertFalse($customers['receives_reminders']['nullable']);
        $this->assertSame('1', $customers['receives_reminders']['default']);
    }
}
