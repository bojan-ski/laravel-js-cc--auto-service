<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SoftDeletedUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_a_soft_deleted_user_cannot_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->trashed()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_failure_message_does_not_reveal_that_the_account_was_offboarded(): void
    {
        $user = User::factory()->trashed()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertInvalid(['email' => __('auth.failed')]);
    }

    public function test_a_user_soft_deleted_mid_session_is_a_guest_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $user->delete();

        Auth::forgetGuards();

        $this->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_soft_deleted_user_receives_no_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->trashed()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertNothingSent();
    }

    public function test_a_soft_deleted_address_still_gets_the_generic_confirmation(): void
    {
        Notification::fake();

        $user = User::factory()->trashed()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('success', __('app.auth.reset_link_generic'));
    }

    public function test_an_active_user_by_contrast_does_receive_the_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
