<?php

namespace Tests\Feature\Jobs;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobHistoryQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queriesFor(User $user, string $url): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->get($url)->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return $queries;
    }

    public function test_the_customer_history_costs_the_same_whatever_the_job_volume(): void
    {
        $user = User::factory()->receptionist()->create();

        $light = Customer::factory()->create();
        Job::factory()->for(Vehicle::factory()->for($light))->count(3)->create();

        $heavy = Customer::factory()->create();
        Job::factory()->for(Vehicle::factory()->for($heavy))->count(40)->create();

        $lightQueries = $this->queriesFor($user, route('customers.show', $light));
        $heavyQueries = $this->queriesFor($user, route('customers.show', $heavy));

        $this->assertSame(
            count($lightQueries),
            count($heavyQueries),
            'The page must not issue more queries as a customer accumulates jobs.',
        );
    }

    public function test_the_customer_history_counts_rather_than_loading_every_job(): void
    {
        $customer = Customer::factory()->create();
        Job::factory()->for(Vehicle::factory()->for($customer))->count(40)->create();

        $sql = array_column(
            $this->queriesFor(User::factory()->receptionist()->create(), route('customers.show', $customer)),
            'query',
        );

        $this->assertNotEmpty(
            preg_grep('/select count\(\*\).*from `jobs`/i', $sql),
            'The view-all decision must come from a COUNT query.',
        );

        $this->assertNotEmpty(
            preg_grep('/from `jobs`.*limit 10/is', $sql),
            'The history itself must be fetched with a limit of 10.',
        );
    }

    public function test_the_vehicle_history_costs_the_same_whatever_the_job_volume(): void
    {
        $user = User::factory()->receptionist()->create();

        $light = Vehicle::factory()->create();
        Job::factory()->for($light)->count(3)->create();

        $heavy = Vehicle::factory()->create();
        Job::factory()->for($heavy)->count(40)->create();

        $lightQueries = $this->queriesFor($user, route('vehicles.show', $light));
        $heavyQueries = $this->queriesFor($user, route('vehicles.show', $heavy));

        $this->assertSame(
            count($lightQueries),
            count($heavyQueries),
            'The page must not issue more queries as a vehicle accumulates jobs.',
        );
    }

    public function test_the_vehicle_history_counts_rather_than_loading_every_job(): void
    {
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->count(40)->create();

        $sql = array_column(
            $this->queriesFor(User::factory()->receptionist()->create(), route('vehicles.show', $vehicle)),
            'query',
        );

        $this->assertNotEmpty(
            preg_grep('/select count\(\*\).*from `jobs`/i', $sql),
            'The view-all decision must come from a COUNT query.',
        );

        $this->assertNotEmpty(
            preg_grep('/from `jobs`.*limit 10/is', $sql),
            'The history itself must be fetched with a limit of 10.',
        );
    }

    public function test_jobs_sharing_a_timestamp_are_ordered_newest_id_first(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $moment = now()->subDay();

        $first = Job::factory()->for($vehicle)->create(['created_at' => $moment]);
        $second = Job::factory()->for($vehicle)->create(['created_at' => $moment]);
        $third = Job::factory()->for($vehicle)->create(['created_at' => $moment]);

        $jobs = app(JobService::class);

        $this->assertSame(
            [$third->getKey(), $second->getKey(), $first->getKey()],
            $jobs->historyForCustomer($customer)->modelKeys(),
            'Identical timestamps must fall back to a descending id, not database order.',
        );

        $this->assertSame(
            [$third->getKey(), $second->getKey(), $first->getKey()],
            $jobs->historyForVehicle($vehicle)->modelKeys(),
        );
    }

    public function test_the_rendered_pages_order_same_second_jobs_newest_id_first(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $moment = now()->subDay();

        $older = Job::factory()->for($vehicle)->create(['created_at' => $moment]);
        $newer = Job::factory()->for($vehicle)->create(['created_at' => $moment]);

        $user = User::factory()->receptionist()->create();

        foreach ([route('customers.show', $customer), route('vehicles.show', $vehicle)] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertLessThan(
                strpos($html, 'href="'.route('jobs.show', $older).'"'),
                strpos($html, 'href="'.route('jobs.show', $newer).'"'),
                "The higher id must render first at {$url}.",
            );
        }
    }
}
