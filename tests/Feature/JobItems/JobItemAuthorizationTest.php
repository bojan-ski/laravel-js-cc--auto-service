<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobItemAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function workingStateProvider(): array
    {
        return [
            'received' => ['received'],
            'in progress' => ['inProgress'],
            'awaiting parts' => ['awaitingParts'],
        ];
    }

    /**
     * @return array<string, array{0: JobStatus}>
     */
    public static function terminalStateProvider(): array
    {
        return [
            'completed' => [JobStatus::Completed],
            'cancelled' => [JobStatus::Cancelled],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_may_add_a_line_to_a_working_job(string $role): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.items.store', $job), $this->payload())
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(1, JobItem::query()->where('job_id', $job->getKey())->count());
    }

    #[DataProvider('workingStateProvider')]
    public function test_a_line_may_be_added_in_every_working_state(string $state): void
    {
        $job = Job::factory()->{$state}()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.items.store', $job), $this->payload())
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(1, JobItem::query()->where('job_id', $job->getKey())->count());
    }

    #[DataProvider('workingStateProvider')]
    public function test_a_line_may_be_edited_and_deleted_in_every_working_state(string $state): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->{$state}()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();

        $this->assertTrue($mechanic->can('update', $item));
        $this->assertTrue($mechanic->can('delete', $item));

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), $this->payload(['description' => 'Edited while '.$state]))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('Edited while '.$state, $item->fresh()->description);

        $this->actingAs($mechanic)
            ->delete(route('job-items.destroy', $item))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertNull(JobItem::query()->find($item->getKey()));
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_may_edit_and_delete_a_line_assigned_to_them(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($user)->create();

        $this->assertTrue($user->can('update', $item));
        $this->assertTrue($user->can('delete', $item));

        $this->actingAs($user)
            ->put(route('job-items.update', $item), $this->payload(['description' => 'Edited by owner']))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('Edited by owner', $item->fresh()->description);

        $this->actingAs($user)
            ->delete(route('job-items.destroy', $item))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertNull(JobItem::query()->find($item->getKey()));
    }

    public function test_a_mechanic_is_forbidden_from_editing_a_colleagues_line(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Colleague work']);

        $this->assertFalse($mechanic->can('update', $item));

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), $this->payload(['description' => 'Stolen']))
            ->assertForbidden();

        $this->assertSame('Colleague work', $item->fresh()->description);
    }

    public function test_a_mechanic_is_forbidden_from_deleting_a_colleagues_line(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->assertFalse($mechanic->can('delete', $item));

        $this->actingAs($mechanic)
            ->delete(route('job-items.destroy', $item))
            ->assertForbidden();

        $this->assertNotNull(JobItem::query()->find($item->getKey()));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return [
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_edit_and_delete_a_line_belonging_to_someone_else(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->inProgress()->create();
        $first = JobItem::factory()->forJob($job)->create();
        $second = JobItem::factory()->forJob($job)->create();

        $this->assertTrue($user->can('update', $first));

        $this->actingAs($user)
            ->put(route('job-items.update', $first), $this->payload(['description' => 'Corrected by front office']))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('Corrected by front office', $first->fresh()->description);

        $this->actingAs($user)
            ->delete(route('job-items.destroy', $second))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertNull(JobItem::query()->find($second->getKey()));
    }

    #[DataProvider('roleProvider')]
    public function test_every_write_route_is_refused_on_a_locked_job(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($user)->create(['description' => 'Before the lock']);

        $job->locked_at = now();
        $job->save();

        $this->assertFalse($user->can('update', $item));
        $this->assertFalse($user->can('delete', $item));

        $this->actingAs($user)->post(route('jobs.items.store', $job), $this->payload())->assertForbidden();
        $this->actingAs($user)->put(route('job-items.update', $item), $this->payload())->assertForbidden();
        $this->actingAs($user)->delete(route('job-items.destroy', $item))->assertForbidden();

        $this->assertSame(1, JobItem::query()->where('job_id', $job->getKey())->count());
        $this->assertSame('Before the lock', $item->fresh()->description);
    }

    #[DataProvider('terminalStateProvider')]
    public function test_every_write_route_is_refused_on_a_terminal_job_even_for_an_admin(JobStatus $status): void
    {
        $admin = User::factory()->admin()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Recorded while working']);

        $job->status = $status;
        $job->save();

        $this->assertFalse($admin->can('update', $item));
        $this->assertFalse($admin->can('delete', $item));

        $this->actingAs($admin)->post(route('jobs.items.store', $job), $this->payload())->assertForbidden();
        $this->actingAs($admin)->put(route('job-items.update', $item), $this->payload())->assertForbidden();
        $this->actingAs($admin)->delete(route('job-items.destroy', $item))->assertForbidden();

        $this->assertSame(1, JobItem::query()->where('job_id', $job->getKey())->count());
        $this->assertSame('Recorded while working', $item->fresh()->description);
    }

    public function test_a_guest_reaches_none_of_the_three_routes(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->post(route('jobs.items.store', $job), $this->payload())->assertRedirect(route('login'));
        $this->put(route('job-items.update', $item), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('job-items.destroy', $item))->assertRedirect(route('login'));

        $this->assertSame(1, JobItem::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Brake service',
            'quantity' => '1.5',
            'user_id' => User::factory()->mechanic()->create()->getKey(),
        ], $overrides);
    }
}
