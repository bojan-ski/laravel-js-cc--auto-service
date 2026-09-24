<?php

namespace Tests\Feature\Customers;

use App\Enums\Locale;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CustomerIntakeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerIntakeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfDay()->setTime(9, 0));
        $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function book(array $party): TestResponse
    {
        return $this->from(route('appointments.create'))->post(route('appointments.store'), [
            ...$party,
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
        ]);
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function walkIn(array $party): TestResponse
    {
        return $this->from(route('jobs.create'))->post(route('jobs.store'), [...$party, 'complaint' => 'Squeaks']);
    }

    /**
     * @return array<string, string>
     */
    private function newCustomer(): array
    {
        return ['new_customer_name' => 'Ana Anić', 'new_customer_phone' => '065 777 888', 'new_customer_email' => 'ana@example.com'];
    }

    /**
     * @return array<string, string>
     */
    private function newVehicle(): array
    {
        return ['new_vehicle_plate' => 'BG-900-AA', 'new_vehicle_make' => 'Fiat', 'new_vehicle_model' => 'Punto', 'new_vehicle_year' => '2011'];
    }

    public function test_an_existing_vehicle_is_booked_as_before(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->book(['vehicle_id' => $vehicle->getKey()])->assertSessionHasNoErrors();

        $appointment = Appointment::query()->sole();
        $this->assertSame($vehicle->getKey(), $appointment->vehicle_id);
        $this->assertSame($vehicle->customer_id, $appointment->customer_id);
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_a_new_customer_and_vehicle_are_created_with_the_booking(): void
    {
        $this->book([...$this->newCustomer(), ...$this->newVehicle()])->assertSessionHasNoErrors();

        $customer = Customer::query()->sole();
        $vehicle = Vehicle::query()->sole();
        $appointment = Appointment::query()->sole();

        $this->assertSame(['Ana Anić', '065 777 888', 'ana@example.com'], [$customer->name, $customer->phone, $customer->email]);
        $this->assertSame(['BG-900-AA', 'Fiat', 'Punto', 2011], [$vehicle->registration_plate, $vehicle->make, $vehicle->model, $vehicle->year]);
        $this->assertTrue($vehicle->customer->is($customer));
        $this->assertSame([$customer->getKey(), $vehicle->getKey()], [$appointment->customer_id, $appointment->vehicle_id]);
    }

    public function test_an_existing_customer_gets_a_new_vehicle_with_the_booking(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->for($customer)->create();

        $this->book(['customer_id' => $customer->getKey(), ...$this->newVehicle()])->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, $customer->vehicles()->count());
        $this->assertSame('BG-900-AA', Appointment::query()->sole()->vehicle->registration_plate);
    }

    public function test_the_new_customers_fields_are_required_when_nothing_is_picked(): void
    {
        $this->book([])
            ->assertSessionHasErrors([
                'new_customer_name' => __('customers.intake.required_customer', ['attribute' => 'name']),
                'new_customer_phone',
                'new_vehicle_plate' => __('customers.intake.required_vehicle', ['attribute' => 'registration plate']),
                'new_vehicle_make',
                'new_vehicle_model',
            ]);

        $this->assertSame(0, Appointment::query()->count());
    }

    /**
     * The field names inside these messages come from each language's own
     * lang file. English lowercases them mid-sentence; German capitalises
     * every noun, so case-folding them in code would print "kennzeichen".
     */
    public function test_the_missing_field_is_named_in_the_users_language_and_casing(): void
    {
        $german = User::factory()->receptionist()->create();
        $german->locale = Locale::De;
        $german->save();

        $this->actingAs($german);

        $this->book([])->assertSessionHasErrors([
            'new_customer_name' => __('customers.intake.required_customer', ['attribute' => 'Name'], 'de'),
            'new_vehicle_plate' => __('customers.intake.required_vehicle', ['attribute' => 'Kennzeichen'], 'de'),
        ]);
    }

    public function test_an_existing_customer_needs_no_customer_fields_but_still_needs_a_vehicle(): void
    {
        $customer = Customer::factory()->create();

        $this->book(['customer_id' => $customer->getKey()])
            ->assertSessionDoesntHaveErrors(['new_customer_name', 'new_customer_phone'])
            ->assertSessionHasErrors(['new_vehicle_plate', 'new_vehicle_make', 'new_vehicle_model']);
    }

    public function test_a_taken_phone_blocks_the_booking_and_names_the_customer(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '065-777-888']);

        $this->book([...$this->newCustomer(), ...$this->newVehicle()])
            ->assertSessionHasErrors(['new_customer_phone' => __('customers.validation.phone_taken', ['name' => 'Milan Petrović'])]);

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(0, Vehicle::query()->count());
        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_taken_plate_blocks_the_booking_and_names_its_owner(): void
    {
        Vehicle::factory()->for(Customer::factory()->create(['name' => 'Milan Petrović']))->create(['registration_plate' => 'BG-900-AA']);

        $this->book([...$this->newCustomer(), ...$this->newVehicle()])
            ->assertSessionHasErrors(['new_vehicle_plate' => __('customers.intake.plate_taken', ['plate' => 'BG-900-AA', 'owner' => 'Milan Petrović'])]);

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_a_deleted_vehicles_plate_is_reported_as_such(): void
    {
        Vehicle::factory()->for(Customer::factory()->create(['name' => 'Milan Petrović']))->create(['registration_plate' => 'BG-900-AA'])->delete();

        $this->book([...$this->newCustomer(), ...$this->newVehicle()])
            ->assertSessionHasErrors(['new_vehicle_plate' => __('vehicles.validation.plate_deleted', ['owner' => 'Milan Petrović'])]);
    }

    public function test_a_vehicle_that_is_not_the_picked_customers_is_refused(): void
    {
        $customer = Customer::factory()->create();
        $someoneElses = Vehicle::factory()->create();

        $this->book(['customer_id' => $customer->getKey(), 'vehicle_id' => $someoneElses->getKey()])
            ->assertSessionHasErrors(['vehicle_id' => __('customers.intake.vehicle_not_theirs')]);

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_an_existing_vehicle_cannot_be_combined_with_new_customer_details(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->book(['vehicle_id' => $vehicle->getKey(), ...$this->newCustomer()])->assertSessionHasErrors('vehicle_id');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_picked_customer_cannot_be_combined_with_new_customer_details(): void
    {
        $customer = Customer::factory()->create();

        $this->book(['customer_id' => $customer->getKey(), ...$this->newCustomer(), ...$this->newVehicle()])
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_a_soft_deleted_customer_cannot_be_picked(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->book(['customer_id' => $customer->getKey(), ...$this->newVehicle()])->assertSessionHasErrors('customer_id');
    }

    public function test_a_failed_booking_leaves_no_new_customer_or_vehicle_behind(): void
    {
        $this->expectException(QueryException::class);

        try {
            app(CustomerIntakeService::class)->book(
                ['customer_id' => null, 'vehicle_id' => null, 'customer' => ['name' => 'Ana Anić', 'phone' => '065 777 888', 'email' => null], 'vehicle' => ['registration_plate' => 'BG-900-AA', 'make' => 'Fiat', 'model' => 'Punto', 'year' => null]],
                new User,
                ['scheduled_at' => now()->addDay(), 'duration_minutes' => 60],
            );
        } finally {
            $this->assertSame(0, Customer::query()->count());
            $this->assertSame(0, Vehicle::query()->count());
        }
    }

    public function test_a_walk_in_creates_the_new_customer_and_vehicle_and_snapshots_them_on_the_job(): void
    {
        $this->walkIn([...$this->newCustomer(), ...$this->newVehicle()])->assertSessionHasNoErrors();

        $job = Job::query()->sole();

        $this->assertSame(Customer::query()->sole()->getKey(), $job->customer_id);
        $this->assertSame(Vehicle::query()->sole()->getKey(), $job->vehicle_id);
    }

    public function test_a_walk_in_for_an_existing_customer_adds_their_new_vehicle(): void
    {
        $customer = Customer::factory()->create();

        $this->walkIn(['customer_id' => $customer->getKey(), ...$this->newVehicle()])->assertSessionHasNoErrors();

        $this->assertSame($customer->getKey(), Job::query()->sole()->customer_id);
        $this->assertSame(1, $customer->vehicles()->count());
    }

    public function test_a_failed_walk_in_leaves_no_new_customer_or_vehicle_behind(): void
    {
        $this->expectException(QueryException::class);

        try {
            app(CustomerIntakeService::class)->openWalkIn(
                ['customer_id' => null, 'vehicle_id' => null, 'customer' => ['name' => 'Ana Anić', 'phone' => '065 777 888', 'email' => null], 'vehicle' => ['registration_plate' => 'BG-900-AA', 'make' => 'Fiat', 'model' => 'Punto', 'year' => null]],
                new User,
                ['complaint' => 'Squeaks'],
            );
        } finally {
            $this->assertSame(0, Customer::query()->count());
            $this->assertSame(0, Vehicle::query()->count());
        }
    }

    public function test_arrival_from_a_booking_refuses_new_customer_details(): void
    {
        $appointment = Appointment::factory()->create();

        $this->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'x', ...$this->newCustomer()])
            ->assertSessionHasErrors('appointment_id');

        $this->assertSame(0, Job::query()->count());
    }
}
