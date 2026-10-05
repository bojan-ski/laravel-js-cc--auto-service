<?php

namespace Tests\Feature\Auth;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Sign-out invalidates the session, so the login page renders in the shop
 * language. The flash must be in that same language, label and body alike,
 * never the body in the signed-out user's language (UAT F-6-09).
 */
class LogoutFlashLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function germanUser(): User
    {
        $user = User::factory()->create();
        $user->locale = Locale::De;
        $user->save();

        return $user;
    }

    private function signOutAndReadFlash(User $user): string
    {
        $html = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('logout'))
            ->assertOk()
            ->getContent();

        preg_match('/<div\b[^>]*data-flash="success".*?<\/p>/s', $html, $flash);

        $this->assertNotEmpty($flash, 'The login page shows no sign-out flash.');

        return $flash[0];
    }

    public function test_the_sign_out_flash_is_in_the_shop_language_like_the_login_page(): void
    {
        $flash = $this->signOutAndReadFlash($this->germanUser());

        $this->assertStringContainsString('Success', $flash);
        $this->assertStringContainsString('You have been signed out.', $flash);
        $this->assertStringNotContainsString('Sie wurden abgemeldet.', $flash);
    }

    public function test_the_sign_out_flash_follows_the_shop_language_not_english(): void
    {
        config(['shop.locale' => 'sr']);

        $flash = $this->signOutAndReadFlash($this->germanUser());

        $this->assertStringContainsString('Uspeh', $flash);
        $this->assertStringContainsString('Odjavljeni ste.', $flash);
        $this->assertStringNotContainsString('Sie wurden abgemeldet.', $flash);
    }
}
