<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_authenticated_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logout_flashes_a_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertSessionHas('success', __('app.auth.flash.logged_out'));
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->startSession();
        $before = session()->getId();

        $this->post('/logout');

        $this->assertNotSame($before, session()->getId());
    }

    public function test_logout_is_not_reachable_by_get(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/logout')
            ->assertMethodNotAllowed();
    }

    public function test_a_guest_cannot_reach_the_logout_route(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }
}
