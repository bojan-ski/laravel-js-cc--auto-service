<?php

namespace Tests\Feature\Reminders;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * No fake: Mail::fake() and Queue::fake() record immediately and never consult
 * `db.transactions`, so neither can see whether the send waited for the commit.
 */
class ReminderAfterCommitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    private function dueVehicle(): void
    {
        Vehicle::factory()
            ->for(Customer::factory()->create(['email' => 'owner@example.test']))
            ->create(['next_service_on' => now()->addDays(5)]);
    }

    public function test_a_run_leaves_one_queued_mail_per_reminder(): void
    {
        $this->dueVehicle();

        app(ReminderService::class)->sendDue(CarbonImmutable::today());

        $this->assertSame(1, DB::table('queue_jobs')->count());
        $this->assertSame(1, VehicleReminder::query()->count());
    }

    /**
     * The discriminating assertion for $afterCommit on the `database` driver:
     * the queue row is absent while the surrounding transaction is open, and
     * present once it commits. A row count after a rollback cannot tell.
     */
    public function test_the_mail_is_released_only_after_its_row_commits(): void
    {
        $this->dueVehicle();

        DB::transaction(function (): void {
            app(ReminderService::class)->sendDue(CarbonImmutable::today());

            $this->assertSame(1, VehicleReminder::query()->count());
            $this->assertSame(0, DB::table('queue_jobs')->count(), 'The mail was queued before its reminder row committed.');
        });

        $this->assertSame(1, DB::table('queue_jobs')->count());
    }
}
