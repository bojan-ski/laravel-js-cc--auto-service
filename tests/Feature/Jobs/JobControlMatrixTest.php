<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobControlMatrixTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VERBS = ['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'];

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function matrixProvider(): array
    {
        $expected = [
            'received' => [
                'mechanic' => ['start'],
                'receptionist' => ['cancel'],
                'admin' => ['start', 'cancel'],
            ],
            'inProgress' => [
                'mechanic' => ['pause', 'complete'],
                'receptionist' => ['pause', 'complete', 'cancel'],
                'admin' => ['pause', 'complete', 'cancel'],
            ],
            'awaitingParts' => [
                'mechanic' => ['resume'],
                'receptionist' => ['cancel'],
                'admin' => ['resume', 'cancel'],
            ],
            'completed' => [
                'mechanic' => [],
                'receptionist' => ['reopen'],
                'admin' => ['reopen'],
            ],
            'cancelled' => [
                'mechanic' => [],
                'receptionist' => [],
                'admin' => [],
            ],
        ];

        $cases = [];

        foreach ($expected as $state => $roles) {
            foreach ($roles as $role => $verbs) {
                $cases["{$state} as {$role}"] = [$state, $role, $verbs];
            }
        }

        return $cases;
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('matrixProvider')]
    public function test_the_control_row_shows_exactly_the_legal_transitions(string $state, string $role, array $expected): void
    {
        $job = $this->jobWithLineItem($state);

        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        foreach (self::VERBS as $verb) {
            $action = route("jobs.{$verb}", $job);

            in_array($verb, $expected, true)
                ? $response->assertSee('action="'.$action.'"', false)
                : $response->assertDontSee('action="'.$action.'"', false);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function withoutControlsProvider(): array
    {
        $cases = [];

        foreach (self::matrixProvider() as $name => [$state, $role, $verbs]) {
            if ($verbs === []) {
                $cases[$name] = [$state, $role];
            }
        }

        return $cases;
    }

    #[DataProvider('withoutControlsProvider')]
    public function test_a_job_with_no_legal_transition_renders_no_control_row_at_all(string $state, string $role): void
    {
        $job = $this->jobWithLineItem($state);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('mt-4 flex flex-wrap gap-2', false);
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('matrixProvider')]
    public function test_every_transition_absent_from_the_control_row_is_refused_by_its_route(string $state, string $role, array $expected): void
    {
        $job = $this->jobWithLineItem($state);
        $user = User::factory()->{$role}()->create();
        $status = $job->status;

        foreach (self::VERBS as $verb) {
            if (in_array($verb, $expected, true)) {
                continue;
            }

            $this->actingAs($user)
                ->patch(route("jobs.{$verb}", $job))
                ->assertForbidden();
        }

        $this->assertSame($status, $job->refresh()->status);
    }

    /**
     * The Issue control is a GET link rather than one of the six PATCH forms, so
     * it needs its own row. It appears on a completed job that carries a line
     * item and holds no invoice, and only for front office.
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function issueProvider(): array
    {
        $cases = [];

        foreach (self::matrixProvider() as $name => [$state, $role]) {
            $cases[$name] = [$state, $role, $state === 'completed' && $role !== 'mechanic'];
        }

        return $cases;
    }

    #[DataProvider('issueProvider')]
    public function test_the_issue_control_appears_exactly_when_the_job_may_be_invoiced(string $state, string $role, bool $expected): void
    {
        $job = $this->jobWithLineItem($state);
        $user = User::factory()->{$role}()->create();
        $link = 'href="'.route('jobs.invoice.create', $job).'"';

        $response = $this->actingAs($user)->get(route('jobs.show', $job))->assertOk();

        $expected
            ? $response->assertSee($link, false)
            : $response->assertDontSee($link, false);

        $this->actingAs($user)
            ->get(route('jobs.invoice.create', $job))
            ->assertStatus($expected ? 200 : 403);
    }

    public function test_a_mechanic_never_sees_cancel_or_reopen(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        foreach (['received', 'inProgress', 'awaitingParts', 'completed', 'cancelled'] as $state) {
            $job = $this->jobWithLineItem($state);

            $this->actingAs($mechanic)
                ->get(route('jobs.show', $job))
                ->assertOk()
                ->assertDontSee('action="'.route('jobs.cancel', $job).'"', false)
                ->assertDontSee('action="'.route('jobs.reopen', $job).'"', false);
        }
    }

    /**
     * Every job here carries a line item so that its status alone decides which
     * controls appear; the lineless case is covered by
     * JobCompletionRequiresLineItemsTest.
     */
    private function jobWithLineItem(string $state): Job
    {
        $job = Job::factory()->{$state}()->create();

        JobItem::factory()->forJob($job)->create();

        return $job;
    }
}
