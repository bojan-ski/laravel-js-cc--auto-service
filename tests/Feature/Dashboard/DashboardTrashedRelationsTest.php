<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * `HasHistoricalOwnership::vehicle()` is already `->withTrashed()`, so this passes
 * on the first run. It is written to fail if that relation is ever swapped for a
 * plain `belongsTo`: the eager load would then return nothing and every row would
 * fatal on a null vehicle, on the one page every staff member opens first.
 */
class DashboardTrashedRelationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_page_renders_when_every_related_customer_and_vehicle_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123']);

        Appointment::factory()->for($customer)->for($vehicle)->create([
            'scheduled_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        Job::factory()->for($customer)->for($vehicle)->received()->create();

        $vehicle->delete();
        $customer->delete();

        $this->assertTrue($vehicle->fresh()->trashed());
        $this->assertTrue($customer->fresh()->trashed());

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('BG123')
            ->assertDontSee(__('dashboard.today.empty'))
            ->assertDontSee(__('dashboard.active_jobs.empty'));
    }
}
