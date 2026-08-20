<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    private function failLogin(string $email): void
    {
        $this->post('/login', [
            'email' => $email,
            'password' => 'not-the-password',
        ]);
    }

    public function test_the_sixth_attempt_within_the_window_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin($user->email);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertInvalid(['email' => 'Too many attempts']);
    }

    public function test_the_throttled_attempt_dispatches_the_lockout_event(): void
    {
        Event::fake([Lockout::class]);

        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->failLogin($user->email);
        }

        Event::assertDispatched(Lockout::class);
    }

    public function test_attempts_below_the_limit_do_not_dispatch_lockout(): void
    {
        Event::fake([Lockout::class]);

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin($user->email);
        }

        Event::assertNotDispatched(Lockout::class);
    }

    public function test_a_successful_login_clears_the_limiter(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin($user->email);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout');

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin($user->email);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertInvalid(['email' => __('auth.failed')]);
    }

    public function test_correct_credentials_are_still_refused_once_the_limit_is_reached(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin($user->email);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertInvalid(['email' => 'Too many attempts']);

        $this->assertGuest();
    }

    public function test_the_limiter_is_keyed_per_email_so_another_address_is_unaffected(): void
    {
        $victim = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->failLogin($victim->email);
        }

        $this->post('/login', [
            'email' => $other->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_casing_cannot_be_used_to_obtain_fresh_attempts(): void
    {
        $user = User::factory()->create(['email' => 'bojan@auto-service.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin($user->email);
        }

        $this->post('/login', [
            'email' => 'BOJAN@AUTO-SERVICE.TEST',
            'password' => 'not-the-password',
        ])->assertInvalid(['email' => 'Too many attempts']);
    }
}
