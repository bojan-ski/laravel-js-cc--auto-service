<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function routeGuardedBy(string $middleware): string
    {
        $uri = '/role-probe-'.str_replace([':', ','], '-', $middleware);

        Route::middleware(['web', 'auth', $middleware])->get($uri, fn () => 'ok');

        return $uri;
    }

    public function test_it_permits_a_user_whose_role_is_listed(): void
    {
        $uri = $this->routeGuardedBy('role:admin');

        $this->actingAs(User::factory()->admin()->create())
            ->get($uri)
            ->assertOk();
    }

    public function test_it_forbids_a_user_whose_role_is_not_listed(): void
    {
        $uri = $this->routeGuardedBy('role:admin');

        $this->actingAs(User::factory()->mechanic()->create())
            ->get($uri)
            ->assertForbidden();
    }

    public function test_it_accepts_a_list_of_roles(): void
    {
        $uri = $this->routeGuardedBy('role:receptionist,admin');

        $this->actingAs(User::factory()->receptionist()->create())->get($uri)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($uri)->assertOk();
    }

    public function test_a_role_outside_a_multi_role_list_is_still_forbidden(): void
    {
        $uri = $this->routeGuardedBy('role:receptionist,admin');

        $this->actingAs(User::factory()->mechanic()->create())
            ->get($uri)
            ->assertForbidden();
    }

    public function test_a_guest_never_reaches_the_role_middleware(): void
    {
        $uri = $this->routeGuardedBy('role:admin');

        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_a_refusal_renders_the_styled_forbidden_view(): void
    {
        $uri = $this->routeGuardedBy('role:admin');

        $this->actingAs(User::factory()->mechanic()->create())
            ->get($uri)
            ->assertForbidden()
            ->assertSee(__('errors.forbidden.title'))
            ->assertSee(__('errors.forbidden.message'));
    }
}
