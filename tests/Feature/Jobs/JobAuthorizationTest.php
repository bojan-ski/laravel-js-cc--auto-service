<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array ROLES = ['mechanic', 'receptionist', 'admin'];

    /**
     * The factory state in which each transition ability's guard is true.
     *
     * @var array<string, string>
     */
    private const array ENABLING_STATE = [
        'start' => 'received',
        'pause' => 'inProgress',
        'resume' => 'awaitingParts',
        'complete' => 'inProgress',
        'cancel' => 'received',
        'reopen' => 'completed',
    ];

    /**
     * Every role crossed with every ability, each on a job in its enabling state.
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function abilityProvider(): array
    {
        $matrix = [
            'mechanic' => [
                'viewAny' => true, 'view' => true, 'create' => false,
                'start' => true, 'pause' => true, 'resume' => true, 'complete' => true,
                'cancel' => false, 'reopen' => false,
            ],
            'receptionist' => [
                'viewAny' => true, 'view' => true, 'create' => true,
                'start' => true, 'pause' => true, 'resume' => true, 'complete' => true,
                'cancel' => true, 'reopen' => true,
            ],
            'admin' => [
                'viewAny' => true, 'view' => true, 'create' => true,
                'start' => true, 'pause' => true, 'resume' => true, 'complete' => true,
                'cancel' => true, 'reopen' => true,
            ],
        ];

        $cases = [];

        foreach ($matrix as $role => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                $cases["a {$role} ".($allowed ? 'may' : 'may not')." {$ability}"] = [$role, $ability, $allowed];
            }
        }

        return $cases;
    }

    #[DataProvider('abilityProvider')]
    public function test_the_policy_matrix_holds_when_the_guard_is_satisfied(string $role, string $ability, bool $allowed): void
    {
        $user = User::factory()->{$role}()->create();

        $subject = match ($ability) {
            'viewAny', 'create' => Job::class,
            'view' => Job::factory()->create(),
            default => $this->jobFor($ability, self::ENABLING_STATE[$ability]),
        };

        $this->assertSame($allowed, $user->can($ability, $subject));
    }

    /**
     * Every transition ability crossed with every role and every state in which
     * its guard is false.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function disabledStateProvider(): array
    {
        $states = ['received', 'inProgress', 'awaitingParts', 'completed', 'cancelled'];
        $cases = [];

        foreach (self::ENABLING_STATE as $ability => $enabling) {
            $legalStates = $ability === 'cancel' ? ['received', 'inProgress', 'awaitingParts'] : [$enabling];

            foreach (array_diff($states, $legalStates) as $state) {
                foreach (self::ROLES as $role) {
                    $cases["{$role} may not {$ability} a {$state} job"] = [$role, $ability, $state];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('disabledStateProvider')]
    public function test_every_transition_ability_is_denied_when_its_guard_is_false(string $role, string $ability, string $state): void
    {
        $user = User::factory()->{$role}()->create();
        $job = $this->jobFor($ability, $state);

        $this->assertFalse($user->can($ability, $job));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lockedProvider(): array
    {
        $cases = [];

        foreach (self::ENABLING_STATE as $ability => $state) {
            foreach (self::ROLES as $role) {
                $cases["{$role} may not {$ability} a locked job"] = [$role, $ability];
            }
        }

        return $cases;
    }

    #[DataProvider('lockedProvider')]
    public function test_every_transition_ability_is_denied_on_a_locked_job_even_for_an_admin(string $role, string $ability): void
    {
        $user = User::factory()->{$role}()->create();
        $job = $this->jobFor($ability, self::ENABLING_STATE[$ability], locked: true);

        $this->assertFalse($user->can($ability, $job));
    }

    public function test_a_locked_job_may_still_be_viewed(): void
    {
        $job = Job::factory()->locked()->create();

        foreach (self::ROLES as $role) {
            $this->assertTrue(User::factory()->{$role}()->create()->can('view', $job));
        }
    }

    public function test_the_policy_defines_no_update_or_delete_ability(): void
    {
        $admin = User::factory()->admin()->create();
        $job = Job::factory()->create();

        $this->assertFalse($admin->can('update', $job));
        $this->assertFalse($admin->can('delete', $job));
    }

    /**
     * A job in the given state, carrying a line item whenever the ability under
     * test is `complete`, so that only its status decides the outcome.
     */
    private function jobFor(string $ability, string $state, bool $locked = false): Job
    {
        $job = Job::factory()->{$state}()->create();

        if ($ability === 'complete') {
            JobItem::factory()->forJob($job)->create();
        }

        if ($locked) {
            $job->locked_at = now();
            $job->save();
        }

        return $job;
    }
}
