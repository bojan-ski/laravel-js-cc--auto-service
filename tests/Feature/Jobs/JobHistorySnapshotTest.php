<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsJobLinks;
use Tests\TestCase;

class JobHistorySnapshotTest extends TestCase
{
    use AssertsJobLinks;
    use LazilyRefreshDatabase;

    public function test_a_sold_car_leaves_its_history_with_the_customer_who_paid_for_it(): void
    {
        $seller = Customer::factory()->create(['name' => 'Milan Petrović']);
        $buyer = Customer::factory()->create(['name' => 'Jelena Ilić']);
        $vehicle = Vehicle::factory()->for($seller)->create(['registration_plate' => 'BG123-ZZ']);

        $job = Job::factory()->for($vehicle)->create();

        app(VehicleService::class)->reassign($vehicle, $buyer);

        $this->assertSame($buyer->getKey(), $vehicle->fresh()->customer_id);
        $this->assertSame($seller->getKey(), $job->fresh()->customer_id);

        $staff = User::factory()->receptionist()->create();

        $this->actingAs($staff)
            ->get(route('customers.show', $seller))
            ->assertOk()
            ->assertSee($this->jobLink($job), false);

        $this->actingAs($staff)
            ->get(route('customers.show', $buyer))
            ->assertOk()
            ->assertSee(__('customers.show.jobs_empty'))
            ->assertDontSee($this->jobLink($job), false);

        $this->actingAs($staff)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($this->jobLink($job), false)
            ->assertSee('Milan Petrović');
    }

    public function test_a_service_history_spans_owners_and_names_each_one(): void
    {
        $seller = Customer::factory()->create(['name' => 'Milan Petrović']);
        $buyer = Customer::factory()->create(['name' => 'Jelena Ilić']);
        $vehicle = Vehicle::factory()->for($seller)->create();

        $underSeller = Job::factory()->for($vehicle)->create(['created_at' => now()->subYear()]);

        app(VehicleService::class)->reassign($vehicle, $buyer);

        $underBuyer = Job::factory()->for($vehicle)->create(['created_at' => now()]);

        $this->assertSame($seller->getKey(), $underSeller->fresh()->customer_id);
        $this->assertSame($buyer->getKey(), $underBuyer->fresh()->customer_id);

        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($this->jobLink($underSeller), false)
            ->assertSee($this->jobLink($underBuyer), false)
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'Milan Petrović'),
            'The former owner appears nowhere but their own history row.',
        );

        $this->assertSame(
            2,
            substr_count($html, 'Jelena Ilić'),
            'The current owner appears once in the identity section and once in their own history row.',
        );
    }

    public function test_a_new_owner_does_not_inherit_the_previous_owners_jobs(): void
    {
        $seller = Customer::factory()->create();
        $buyer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($seller)->create();

        $before = Job::factory()->for($vehicle)->create();

        app(VehicleService::class)->reassign($vehicle, $buyer);

        $after = Job::factory()->for($vehicle)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $buyer))
            ->assertOk()
            ->assertSee($this->jobLink($after), false)
            ->assertDontSee($this->jobLink($before), false);
    }

    public function test_the_customer_page_renders_when_every_related_record_is_trashed(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $creator = User::factory()->receptionist()->create();
        $job = Job::factory()->for($vehicle)->for($creator, 'createdBy')->create();

        $vehicle->delete();
        $creator->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($this->jobLink($job), false)
            ->assertSee(__('app.deleted'));
    }

    public function test_the_vehicle_page_renders_when_every_related_record_is_trashed(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $creator = User::factory()->receptionist()->create();
        $job = Job::factory()->for($vehicle)->for($creator, 'createdBy')->create();

        $customer->delete();
        $creator->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($this->jobLink($job), false)
            ->assertSee(__('app.deleted'));
    }
}
