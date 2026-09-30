<?php

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Policies\EstimatePolicy;
use App\Policies\JobPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimatePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, bool}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic', false],
            'receptionist' => ['receptionist', true],
            'admin' => ['admin', true],
        ];
    }

    #[DataProvider('roles')]
    public function test_only_front_office_may_view_estimates(string $role, bool $allowed): void
    {
        $user = User::factory()->{$role}()->make();
        $estimate = Estimate::factory()->make();

        $this->assertSame($allowed, (new EstimatePolicy)->viewAny($user));
        $this->assertSame($allowed, (new EstimatePolicy)->view($user, $estimate));
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function states(): array
    {
        return [
            'pending' => ['pending', true, true],
            'approved' => ['approved', false, true],
            'declined' => ['declined', false, false],
            'superseded' => ['superseded', false, false],
        ];
    }

    #[DataProvider('states')]
    public function test_deciding_and_emailing_follow_the_estimate_state_for_front_office(string $state, bool $decide, bool $email): void
    {
        $estimate = Estimate::factory()->{$state}()->make();

        foreach (['receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->make();

            $this->assertSame($decide, (new EstimatePolicy)->decide($user, $estimate), "{$role} decide {$state}");
            $this->assertSame($email, (new EstimatePolicy)->email($user, $estimate), "{$role} email {$state}");
        }

        $mechanic = User::factory()->mechanic()->make();
        $this->assertFalse((new EstimatePolicy)->decide($mechanic, $estimate));
        $this->assertFalse((new EstimatePolicy)->email($mechanic, $estimate));
    }

    public function test_the_policy_offers_no_create_update_or_delete_ability(): void
    {
        foreach (['create', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse(method_exists(EstimatePolicy::class, $ability), $ability);
        }
    }

    public function test_estimating_a_job_needs_front_office_and_a_working_unlocked_job_with_a_line(): void
    {
        $working = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($working)->create();
        $lineless = Job::factory()->inProgress()->create();
        $completed = Job::factory()->completed()->create();
        JobItem::factory()->forJob($completed)->create();
        $cancelled = Job::factory()->cancelled()->create();
        $locked = Job::factory()->locked()->create();
        $policy = new JobPolicy;

        foreach (['receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->assertTrue($policy->estimate($user, $working), $role);
            $this->assertFalse($policy->estimate($user, $lineless), $role);
            $this->assertFalse($policy->estimate($user, $completed), $role);
            $this->assertFalse($policy->estimate($user, $cancelled), $role);
            $this->assertFalse($policy->estimate($user, $locked), $role);
        }

        $this->assertFalse($policy->estimate(User::factory()->mechanic()->create(), $working));
    }
}
