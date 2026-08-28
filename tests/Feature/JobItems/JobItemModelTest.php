<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobItemModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_factory_builds_a_labor_line_at_the_config_rate(): void
    {
        config(['shop.labor_rate' => 2750]);

        $item = JobItem::factory()->create();

        $this->assertTrue($item->isLabor());
        $this->assertFalse($item->isPart());
        $this->assertSame('2750.00', $item->unit_price);
        $this->assertNull($item->part_id);
        $this->assertNotNull($item->user_id);
    }

    public function test_the_part_state_builds_an_unassigned_part_line(): void
    {
        $item = JobItem::factory()->part()->create();

        $this->assertTrue($item->isPart());
        $this->assertFalse($item->isLabor());
        $this->assertSame(JobItemType::Part, $item->type);
        $this->assertNull($item->user_id);
    }

    public function test_ownership_is_decided_by_the_named_user(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();

        $item = JobItem::factory()->performedBy($mechanic)->create();

        $this->assertTrue($item->isOwnedBy($mechanic));
        $this->assertFalse($item->isOwnedBy($colleague));
    }

    public function test_an_unassigned_line_is_owned_by_nobody(): void
    {
        $item = JobItem::factory()->part()->create();

        $this->assertFalse($item->isOwnedBy(User::factory()->mechanic()->create()));
    }

    public function test_the_line_total_multiplies_the_stored_snapshots(): void
    {
        $item = JobItem::factory()->create(['quantity' => '1.50', 'unit_price' => '3000.00']);

        $this->assertSame('4500.00', $item->lineTotal());
    }

    public function test_the_line_total_keeps_two_decimal_places_on_awkward_numbers(): void
    {
        $item = JobItem::factory()->create(['quantity' => '0.33', 'unit_price' => '3000.05']);

        $this->assertSame('990.01', $item->lineTotal());
    }

    public function test_a_line_still_names_its_mechanic_after_that_mechanic_is_soft_deleted(): void
    {
        $mechanic = User::factory()->mechanic()->create(['name' => 'Dragan Jovanović']);
        $item = JobItem::factory()->performedBy($mechanic)->create();

        $mechanic->delete();

        $reloaded = JobItem::query()->with('user')->findOrFail($item->getKey());

        $this->assertNotNull($reloaded->user);
        $this->assertSame('Dragan Jovanović', $reloaded->user->name);
        $this->assertTrue($reloaded->user->trashed());
    }

    public function test_a_jobs_subtotal_sums_its_loaded_line_totals(): void
    {
        $job = Job::factory()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.50', 'unit_price' => '3000.00']);
        JobItem::factory()->forJob($job)->create(['quantity' => '0.50', 'unit_price' => '3000.00']);

        $job->load('items');

        $this->assertSame('6000.00', $job->subtotal());
    }

    public function test_a_job_with_no_lines_has_a_zero_subtotal(): void
    {
        $job = Job::factory()->create();
        $job->load('items');

        $this->assertSame('0.00', $job->subtotal());
    }

    public function test_has_line_items_answers_with_and_without_the_relation_loaded(): void
    {
        $job = Job::factory()->create();

        $this->assertFalse($job->hasLineItems());

        JobItem::factory()->forJob($job)->create();

        $this->assertTrue(Job::query()->findOrFail($job->getKey())->hasLineItems());
        $this->assertTrue(Job::query()->with('items')->findOrFail($job->getKey())->hasLineItems());
    }

    public function test_assigned_names_are_distinct_short_names_from_labor_lines_only(): void
    {
        $job = Job::factory()->create();
        $dragan = User::factory()->mechanic()->create(['name' => 'Dragan Jovanović']);
        $milena = User::factory()->mechanic()->create(['name' => 'Milena Stanković']);

        JobItem::factory()->forJob($job)->performedBy($dragan)->create();
        JobItem::factory()->forJob($job)->performedBy($dragan)->create();
        JobItem::factory()->forJob($job)->performedBy($milena)->create();
        JobItem::factory()->forJob($job)->part()->create();

        $job->load(['laborItems', 'laborItems.user']);

        $this->assertSame(['D. Jovanović', 'M. Stanković'], $job->assignedNames()->all());
    }

    public function test_a_job_with_no_labor_lines_names_nobody(): void
    {
        $job = Job::factory()->create();
        JobItem::factory()->forJob($job)->part()->create();

        $job->load(['laborItems', 'laborItems.user']);

        $this->assertSame([], $job->assignedNames()->all());
    }

    public function test_the_labor_relation_excludes_part_lines(): void
    {
        $job = Job::factory()->create();
        $labor = JobItem::factory()->forJob($job)->create();
        JobItem::factory()->forJob($job)->part()->create();

        $this->assertSame([$labor->getKey()], $job->laborItems()->pluck('id')->all());
        $this->assertCount(2, $job->items()->get());
    }

    public function test_a_short_name_is_the_first_initial_and_the_last_word(): void
    {
        $this->assertSame('D. Jovanović', $this->named('Dragan Jovanović')->shortName());
        $this->assertSame('A. Petrov', $this->named('Ana Marija Petrov')->shortName());
    }

    public function test_a_single_word_name_is_left_whole(): void
    {
        $this->assertSame('Cher', $this->named('Cher')->shortName());
    }

    private function named(string $name): User
    {
        return User::factory()->mechanic()->make(['name' => $name]);
    }
}
