<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NavUserMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_shows_the_authenticated_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Dragan Jovanovic']);

        $this->actingAs($user)->get('/')->assertSee('Dragan Jovanovic');
    }

    public function test_it_shows_the_role_label_rather_than_the_raw_role_value(): void
    {
        $user = User::factory()->receptionist()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(__('app.roles.receptionist'));
        $response->assertDontSee('>receptionist<', false);
    }

    public function test_the_logout_control_posts_to_the_logout_route(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee(__('app.nav.sign_out'));
    }

    public function test_the_disclosure_needs_no_javascript(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertSee('<details', false);
        $response->assertSee('<summary', false);
    }

    public function test_the_menu_carries_a_csrf_token(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('name="_token"', false);
    }
}
