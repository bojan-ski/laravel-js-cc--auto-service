<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobBoardRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyRoleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    public function test_the_board_route_is_named_and_authenticated(): void
    {
        $route = Route::getRoutes()->getByName('jobs.index');

        $this->assertNotNull($route);
        $this->assertSame('jobs', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('jobs.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('everyRoleProvider')]
    public function test_every_role_can_load_the_board(string $role): void
    {
        $job = Job::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertViewIs('pages.jobs.index')
            ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1)
            ->assertSee($job->reference);
    }

    public function test_an_unknown_status_fails_validation(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['status' => 'bogus']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_an_unknown_sort_and_direction_fall_back_to_the_default(): void
    {
        $older = Job::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = Job::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['sort' => 'bogus', 'direction' => 'sideways']))
            ->assertOk()
            ->assertViewHas('filters', ['status' => '', 'search' => '', 'sort' => 'received', 'direction' => 'desc'])
            ->assertViewHas('jobs', fn ($jobs) => $jobs->pluck('id')->all() === [$newer->getKey(), $older->getKey()]);
    }

    public function test_an_overlong_search_fails_validation(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['search' => str_repeat('a', 256)]))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }
}
