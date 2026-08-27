<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobShowRouteTest extends TestCase
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function transitionRouteProvider(): array
    {
        return [
            'start' => ['jobs.start'],
            'pause' => ['jobs.pause'],
            'resume' => ['jobs.resume'],
            'complete' => ['jobs.complete'],
            'cancel' => ['jobs.cancel'],
            'reopen' => ['jobs.reopen'],
        ];
    }

    public function test_all_seven_routes_sit_in_the_auth_band(): void
    {
        $names = ['jobs.show', 'jobs.start', 'jobs.pause', 'jobs.resume', 'jobs.complete', 'jobs.cancel', 'jobs.reopen'];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "{$name} is not registered");
            $this->assertSame(['web', 'auth'], $route->middleware());
        }
    }

    public function test_a_guest_is_redirected_to_login_from_every_route(): void
    {
        $job = Job::factory()->create();

        $this->get(route('jobs.show', $job))->assertRedirect(route('login'));

        foreach (array_keys(self::transitionRouteProvider()) as $verb) {
            $this->patch(route("jobs.{$verb}", $job))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('everyRoleProvider')]
    public function test_every_role_can_view_a_job(string $role): void
    {
        $job = Job::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertViewIs('pages.jobs.show')
            ->assertSee($job->reference);
    }

    public function test_an_unknown_job_returns_the_styled_not_found_page(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', 999_999))
            ->assertNotFound()
            ->assertSee(__('errors.not_found.title'));
    }

    public function test_the_create_form_is_not_bound_as_a_job(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create'))
            ->assertOk()
            ->assertViewIs('pages.jobs.create');
    }
}
