<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_login_page_renders_for_a_guest(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee(__('app.auth.login.submit'));
    }

    public function test_login_page_redirects_an_authenticated_user_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_valid_credentials_authenticate_the_user(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create();

        $this->startSession();
        $before = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_login_redirects_to_the_intended_destination(): void
    {
        $user = User::factory()->create();

        $this->withSession(['url.intended' => url('/jobs')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(url('/jobs'));
    }

    public function test_a_wrong_password_returns_to_the_form_with_an_error_on_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unknown_address_fails_with_the_same_message_as_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertInvalid(['email' => __('auth.failed')]);

        $this->post('/login', [
            'email' => 'nobody@auto-service.test',
            'password' => 'not-the-password',
        ])->assertInvalid(['email' => __('auth.failed')]);
    }

    public function test_remember_me_issues_the_recaller_cookie(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_a_mixed_case_address_still_authenticates(): void
    {
        $user = User::factory()->create(['email' => 'bojan@auto-service.test']);

        $this->post('/login', [
            'email' => '  Bojan@Auto-Service.TEST  ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_role_cannot_be_mass_assigned_through_the_login_endpoint(): void
    {
        $user = User::factory()->mechanic()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'role' => UserRole::Admin->value,
        ]);

        $this->assertSame(UserRole::Mechanic, $user->fresh()->role);
    }
}
