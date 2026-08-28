<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobBoardAssignedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_job_with_no_labor_lines_shows_the_absent_value_placeholder(): void
    {
        Job::factory()->create();

        $this->board()
            ->assertOk()
            ->assertSee(__('app.value_none_spoken'))
            ->assertSee(__('app.value_none'));
    }

    public function test_a_job_with_one_named_mechanic_shows_that_short_name(): void
    {
        $this->jobWorkedBy(['Dragan Jovanović']);

        $this->board()
            ->assertOk()
            ->assertSee('D. Jovanović')
            ->assertDontSee(__('app.value_none_spoken'));
    }

    public function test_two_distinct_mechanics_are_both_named(): void
    {
        $this->jobWorkedBy(['Dragan Jovanović', 'Milena Stanković']);

        $this->board()
            ->assertOk()
            ->assertSee('D. Jovanović')
            ->assertSee('M. Stanković')
            ->assertDontSee('+1');
    }

    public function test_a_third_and_fourth_mechanic_collapse_into_an_overflow_count(): void
    {
        $this->jobWorkedBy([
            'Dragan Jovanović',
            'Milena Stanković',
            'Nikola Ilić',
            'Ana Petrov',
        ]);

        $response = $this->board()->assertOk();

        $response->assertSee('D. Jovanović');
        $response->assertSee('M. Stanković');
        $response->assertSee('+2');
        $response->assertDontSee('N. Ilić');
        $response->assertDontSee('A. Petrov');
    }

    public function test_the_overflow_count_is_readable_rather_than_a_bare_numeral(): void
    {
        $this->jobWorkedBy([
            'Dragan Jovanović',
            'Milena Stanković',
            'Nikola Ilić',
            'Ana Petrov',
        ]);

        $response = $this->board()->assertOk();

        $response->assertSee(trans_choice('jobs.board.assigned_more', 2, ['count' => 2]));
        $response->assertSee('aria-hidden="true">+2<', false);
    }

    public function test_a_single_extra_person_reads_in_the_singular(): void
    {
        $this->jobWorkedBy(['Dragan Jovanović', 'Milena Stanković', 'Nikola Ilić']);

        $this->board()
            ->assertOk()
            ->assertSee(trans_choice('jobs.board.assigned_more', 1, ['count' => 1]));
    }

    public function test_repeated_lines_by_one_mechanic_name_them_once(): void
    {
        $job = Job::factory()->create();
        $mechanic = User::factory()->mechanic()->create(['name' => 'Dragan Jovanović']);

        JobItem::factory()->forJob($job)->performedBy($mechanic)->count(3)->create();

        $response = $this->board()->assertOk();

        $response->assertSee('D. Jovanović');
        $response->assertDontSee('+1');
        $this->assertSame(1, substr_count($response->getContent(), 'D. Jovanović'));
    }

    public function test_a_mechanic_who_has_since_been_offboarded_is_still_named(): void
    {
        $job = Job::factory()->create();
        $mechanic = User::factory()->mechanic()->create(['name' => 'Dragan Jovanović']);
        JobItem::factory()->forJob($job)->performedBy($mechanic)->create();

        $mechanic->delete();

        $this->board()->assertOk()->assertSee('D. Jovanović');
    }

    public function test_the_board_names_nobody_from_a_part_line(): void
    {
        $job = Job::factory()->create();
        JobItem::factory()->forJob($job)->part()->create();

        $this->board()->assertOk()->assertSee(__('app.value_none_spoken'));
    }

    public function test_the_board_renders_no_price_for_any_role(): void
    {
        $job = Job::factory()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.50', 'unit_price' => '3000.00']);

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $response = $this->board(User::factory()->{$role}()->create())->assertOk();

            $response->assertDontSee('3,000.00');
            $response->assertDontSee('4,500.00');
            $response->assertDontSee('3000.00');
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    private function jobWorkedBy(array $names): Job
    {
        $job = Job::factory()->create();

        foreach ($names as $name) {
            JobItem::factory()
                ->forJob($job)
                ->performedBy(User::factory()->mechanic()->create(['name' => $name]))
                ->create();
        }

        return $job;
    }

    private function board(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? User::factory()->mechanic()->create())
            ->get(route('jobs.index'));
    }
}
