<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\JobItemService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobItemServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_mechanics_submitted_user_is_ignored_by_the_service_itself(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $item = $this->service()->addLabor($job, $mechanic, [
            'description' => 'Diagnostics',
            'quantity' => '0.5',
            'user_id' => $colleague->getKey(),
        ]);

        $this->assertSame($mechanic->getKey(), $item->user_id);
    }

    public function test_a_mechanics_submitted_rate_is_ignored_by_the_service_itself(): void
    {
        config(['shop.labor_rate' => 3000]);

        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $item = $this->service()->addLabor($job, $mechanic, [
            'description' => 'Diagnostics',
            'quantity' => '0.5',
            'user_id' => $mechanic->getKey(),
            'unit_price' => '999999',
        ]);

        $this->assertSame('3000.00', $item->unit_price);
    }

    public function test_front_office_may_name_anyone_and_set_the_rate(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $item = $this->service()->addLabor($job, $receptionist, [
            'description' => 'Brake service',
            'quantity' => '1.5',
            'user_id' => $mechanic->getKey(),
            'unit_price' => '4200.50',
        ]);

        $this->assertSame($mechanic->getKey(), $item->user_id);
        $this->assertSame('4200.50', $item->unit_price);
        $this->assertSame(JobItemType::Labor, $item->type);
        $this->assertSame($job->getKey(), $item->job_id);
        $this->assertNull($item->part_id);
    }

    public function test_the_service_never_reassigns_a_line_for_a_mechanic(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();

        $this->service()->update($item, $mechanic, [
            'description' => 'Reworded',
            'quantity' => '2',
            'user_id' => $colleague->getKey(),
        ]);

        $this->assertSame('Reworded', $item->fresh()->description);
        $this->assertSame($mechanic->getKey(), $item->fresh()->user_id);
    }

    public function test_the_service_never_reprices_a_line_for_a_mechanic(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create(['unit_price' => '3000.00']);

        $this->service()->update($item, $mechanic, [
            'description' => 'Reworded',
            'quantity' => '2',
            'user_id' => $mechanic->getKey(),
            'unit_price' => '1',
        ]);

        $this->assertSame('3000.00', $item->fresh()->unit_price);
    }

    public function test_front_office_may_reassign_and_reprice_on_update(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $original = User::factory()->mechanic()->create();
        $replacement = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($original)->create(['unit_price' => '3000.00']);

        $this->service()->update($item, $receptionist, [
            'description' => 'Brake service, both axles',
            'quantity' => '2.5',
            'user_id' => $replacement->getKey(),
            'unit_price' => '3750.25',
        ]);

        $fresh = $item->fresh();

        $this->assertSame($replacement->getKey(), $fresh->user_id);
        $this->assertSame('3750.25', $fresh->unit_price);
        $this->assertSame('2.50', $fresh->quantity);
    }

    public function test_an_update_that_omits_the_rate_keeps_the_stored_snapshot(): void
    {
        config(['shop.labor_rate' => 9999]);

        $receptionist = User::factory()->receptionist()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['unit_price' => '3500.00']);

        $this->service()->update($item, $receptionist, [
            'description' => 'Reworded',
            'quantity' => '2',
            'user_id' => $item->user_id,
        ]);

        $this->assertSame('3500.00', $item->fresh()->unit_price);
    }

    public function test_the_service_hard_deletes(): void
    {
        $item = JobItem::factory()->create();

        $this->service()->delete($item);

        $this->assertSame(0, JobItem::query()->count());
    }

    private function service(): JobItemService
    {
        return app(JobItemService::class);
    }
}
