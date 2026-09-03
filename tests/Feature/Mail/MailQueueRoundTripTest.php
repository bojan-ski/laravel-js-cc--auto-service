<?php

namespace Tests\Feature\Mail;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The only tests that actually serialize a mailable into `queue_jobs` and
 * rehydrate it on a worker. Mail::fake() records the live instance and never
 * round-trips it, so nothing else in the suite can see a restoration failure or
 * a lazy-load violation raised while the queued message renders.
 */
class MailQueueRoundTripTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    private function workOnce(): void
    {
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--no-interaction' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function deliveredBodies(): array
    {
        return Mail::getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage()->toString())
            ->all();
    }

    public function test_a_queued_message_renders_and_sends_on_the_worker(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $customer = Customer::findOrFail($job->customer_id);
        $vehicle = Vehicle::findOrFail($job->vehicle_id);

        app(JobService::class)->complete($job);

        $this->assertSame(1, DB::table('queue_jobs')->count());

        $this->workOnce();

        $bodies = $this->deliveredBodies();

        $this->assertCount(1, $bodies);
        $this->assertStringContainsString($job->reference, $bodies[0]);
        $this->assertStringContainsString($vehicle->registration_plate, $bodies[0]);
        $this->assertSame(0, DB::table('queue_jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertNotNull($customer->email);
    }

    public function test_a_soft_deleted_customer_and_vehicle_still_rehydrate_and_receive(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $customer = Customer::findOrFail($job->customer_id);
        $vehicle = Vehicle::findOrFail($job->vehicle_id);

        $vehicle->delete();
        $customer->delete();

        $this->assertSoftDeleted($customer);
        $this->assertSoftDeleted($vehicle);

        app(JobService::class)->complete($job);

        $this->workOnce();

        $bodies = $this->deliveredBodies();

        $this->assertCount(1, $bodies);
        $this->assertStringContainsString($vehicle->registration_plate, $bodies[0]);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
