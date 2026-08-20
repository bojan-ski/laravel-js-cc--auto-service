<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class NewPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const NEW_PASSWORD = 'a-brand-new-password';

    public function test_the_reset_page_renders_with_the_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get("/reset-password/{$token}?email={$user->email}")
            ->assertOk()
            ->assertViewIs('pages.auth.reset-password')
            ->assertViewHas('token', $token)
            ->assertViewHas('email', $user->email);
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_the_old_password_no_longer_works_after_a_reset(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertFalse(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_successful_reset_flashes_a_confirmation(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHas('success', __('app.auth.flash.password_reset'));
    }

    public function test_a_successful_reset_fires_the_password_reset_event(): void
    {
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        Event::assertDispatched(PasswordReset::class);
    }

    public function test_a_successful_reset_rotates_the_remember_token(): void
    {
        $user = User::factory()->create();
        $before = $user->remember_token;

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertNotSame($before, $user->fresh()->remember_token);
    }

    public function test_a_tampered_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_token_issued_for_another_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($other),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_soft_deleted_user_cannot_reset_their_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $user->delete();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');
    }
}
