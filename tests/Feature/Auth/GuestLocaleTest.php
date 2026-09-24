<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_guest_can_choose_a_language_for_the_session(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'ro'])
            ->assertRedirect(route('login'))
            ->assertSessionHas(SetLocale::SESSION_KEY, 'ro');
    }

    public function test_without_a_previous_page_it_returns_to_login(): void
    {
        $this->post(route('locale.update'), ['locale' => 'de'])->assertRedirect(route('login'));
    }

    public function test_an_unsupported_language_is_refused(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing(SetLocale::SESSION_KEY);
    }

    public function test_a_signed_in_user_is_sent_away(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('locale.update'), ['locale' => 'de'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(SetLocale::SESSION_KEY);
    }

    /**
     * The switcher posts, so it works with JavaScript off; it lists every
     * language by its own name and marks the one in use.
     */
    public function test_the_login_page_offers_every_language(): void
    {
        $html = $this->withSession([SetLocale::SESSION_KEY => 'ro'])->get(route('login'))->assertOk()->getContent();

        preg_match('/<form\b[^>]*action="'.preg_quote(route('locale.update'), '/').'"[^>]*>.*?<\/form>/s', $html, $form);

        $this->assertNotEmpty($form, 'The login page has no language switcher.');
        $this->assertStringNotContainsString('name="_method"', $form[0], 'The guest switcher posts; it must not spoof another method.');
        $this->assertStringContainsString('name="_token"', $form[0]);

        foreach (['English', 'Srpski', 'Română', 'Deutsch'] as $name) {
            $this->assertStringContainsString($name, $form[0]);
        }

        preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*aria-pressed="(true|false)"/', $form[0], $buttons, PREG_SET_ORDER);

        $this->assertSame(['en' => 'false', 'sr' => 'false', 'ro' => 'true', 'de' => 'false'], array_column($buttons, 2, 1));

        preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*\blang="([^"]+)"/', $form[0], $langs, PREG_SET_ORDER);

        $this->assertSame(['en' => 'en', 'sr' => 'sr-Latn', 'ro' => 'ro', 'de' => 'de'], array_column($langs, 2, 1));
    }

    public function test_a_signed_in_users_error_page_has_no_guest_switcher(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/jobs/999999')
            ->assertNotFound()
            ->assertDontSee('action="'.route('locale.update').'"', false);
    }
}
