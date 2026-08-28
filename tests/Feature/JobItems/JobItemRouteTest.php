<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobItemRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_front_office_adds_a_labor_line_assigned_to_the_chosen_user(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.5',
                'user_id' => $mechanic->getKey(),
            ])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.added'));

        $item = JobItem::query()->sole();

        $this->assertSame('Brake service', $item->description);
        $this->assertSame('1.50', $item->quantity);
        $this->assertSame(JobItemType::Labor, $item->type);
        $this->assertSame($mechanic->getKey(), $item->user_id);
        $this->assertNull($item->part_id);
        $this->assertSame('3000.00', $item->unit_price);
    }

    public function test_a_mechanics_submitted_user_id_is_overwritten_with_their_own(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.items.store', $job), [
                'description' => 'Diagnostics',
                'quantity' => '0.5',
                'user_id' => $colleague->getKey(),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame($mechanic->getKey(), JobItem::query()->sole()->user_id);
    }

    public function test_a_mechanic_submitting_a_rate_fails_validation(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.items.store', $job), [
                'description' => 'Diagnostics',
                'quantity' => '0.5',
                'unit_price' => '1',
            ])
            ->assertSessionHasErrors('unit_price');

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_front_office_may_override_the_rate_and_the_snapshot_survives_a_config_change(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('jobs.items.store', $job), [
                'description' => 'Specialist diagnostics',
                'quantity' => '2',
                'user_id' => User::factory()->mechanic()->create()->getKey(),
                'unit_price' => '4500.50',
            ])
            ->assertRedirect(route('jobs.show', $job));

        config(['shop.labor_rate' => 9999]);

        $this->assertSame('4500.50', JobItem::query()->sole()->unit_price);
    }

    public function test_updating_a_line_changes_it_and_redirects_to_its_job(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);
        $other = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), [
                'description' => 'Brake service, both axles',
                'quantity' => '2.25',
                'user_id' => $other->getKey(),
                'unit_price' => '3200',
            ])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.updated'));

        $item->refresh();

        $this->assertSame('Brake service, both axles', $item->description);
        $this->assertSame('2.25', $item->quantity);
        $this->assertSame($other->getKey(), $item->user_id);
        $this->assertSame('3200.00', $item->unit_price);
    }

    public function test_a_mechanic_cannot_reassign_or_reprice_their_own_line(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create(['unit_price' => 3000]);

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), [
                'description' => 'Rewritten',
                'quantity' => '1',
                'user_id' => $colleague->getKey(),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $item->refresh();

        $this->assertSame('Rewritten', $item->description);
        $this->assertSame($mechanic->getKey(), $item->user_id);
        $this->assertSame('3000.00', $item->unit_price);
    }

    public function test_deleting_a_line_removes_it_permanently(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('job-items.destroy', $item))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.deleted'));

        $this->assertSame(0, JobItem::query()->count());
    }
}
