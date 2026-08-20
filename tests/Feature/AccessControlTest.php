<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_guest_is_redirected_from_the_dashboard_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_a_guest_does_not_receive_a_bare_401(): void
    {
        $response = $this->get('/');

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
    }

    public function test_signing_in_returns_the_guest_to_the_destination_they_requested(): void
    {
        Route::middleware(['web', 'auth'])->get('/protected-probe', fn () => 'ok');

        $user = User::factory()->create();

        $this->get('/protected-probe')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/protected-probe');
    }

    public function test_an_authenticated_user_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk();
    }

    public function test_the_health_endpoint_responds_without_authentication(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_authentication_pages_remain_reachable_by_a_guest(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_an_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_an_admin_is_denied_an_ability_that_is_denied_to_everyone(): void
    {
        Gate::define('nobody-may', fn (User $user) => false);

        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('nobody-may'));
    }
}
