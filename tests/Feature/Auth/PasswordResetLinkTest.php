<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    /**
     * @return array{status: int, target: string|null, flash: mixed, errors: array<int, string>}
     */
    private function submit(string $email): array
    {
        $response = $this->post('/forgot-password', ['email' => $email]);

        $session = app('session.store');

        $fingerprint = [
            'status' => $response->getStatusCode(),
            'target' => $response->headers->get('Location'),
            'flash' => $session->get('success'),
            'errors' => $session->has('errors')
                ? $session->get('errors')->getBag('default')->get('email')
                : [],
        ];

        $session->forget('errors');

        return $fingerprint;
    }

    public function test_the_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertViewIs('auth.forgot-password')
            ->assertSee(__('app.auth.forgot.submit'));
    }

    public function test_all_four_outcomes_are_indistinguishable(): void
    {
        Notification::fake();

        $active = User::factory()->create();
        $trashed = User::factory()->trashed()->create();

        $unknown = $this->submit('nobody@auto-service.test');
        $offboarded = $this->submit($trashed->email);
        $firstRequest = $this->submit($active->email);
        $secondRequest = $this->submit($active->email);

        $this->assertSame(__('app.auth.reset_link_generic'), $unknown['flash']);
        $this->assertSame([], $unknown['errors']);

        $this->assertSame($unknown, $offboarded);
        $this->assertSame($unknown, $firstRequest);
        $this->assertSame($unknown, $secondRequest);
    }

    public function test_the_second_request_really_did_hit_the_broker_throttle(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_the_endpoint_is_rate_limited_per_ip_after_five_requests(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->submit($user->email);
        }

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertInvalid(['email' => 'Too many attempts'])
            ->assertSessionMissing('success');
    }

    public function test_the_ip_limit_is_reached_at_the_same_point_whatever_addresses_are_used(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->submit("nobody-{$i}@auto-service.test");
        }

        $this->post('/forgot-password', ['email' => 'someone-else@auto-service.test'])
            ->assertInvalid(['email' => 'Too many attempts'])
            ->assertSessionMissing('success');
    }

    public function test_a_malformed_address_is_rejected_by_validation(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_mixed_case_address_is_normalized_before_the_broker_sees_it(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'bojan@auto-service.test']);

        $this->post('/forgot-password', ['email' => '  Bojan@Auto-Service.TEST  ']);

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
