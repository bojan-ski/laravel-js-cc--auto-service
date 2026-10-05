<?php

namespace Tests\Feature\Preferences;

use App\Enums\Locale;
use App\Enums\Theme;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A URL that matches no route still renders its 404 in the visitor's
 * language (UAT F-6-08): the fallback route puts it through the web group,
 * so the session and the signed-in user reach SetLocale.
 */
class UnmatchedUrlLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function documentLang(string $html): ?string
    {
        preg_match('/<html\b[^>]*\blang="([^"]*)"/', $html, $match);

        return $match[1] ?? null;
    }

    public function test_a_guest_gets_the_unmatched_404_in_the_language_chosen_on_login(): void
    {
        $html = $this->withSession([SetLocale::SESSION_KEY => 'de'])
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Nicht gefunden')
            ->assertSee('Zur Anmeldung')
            ->getContent();

        $this->assertSame('de', $this->documentLang($html));
    }

    public function test_a_guests_register_url_stays_a_styled_404_in_their_language(): void
    {
        $html = $this->withSession([SetLocale::SESSION_KEY => 'de'])
            ->get('/register')
            ->assertNotFound()
            ->assertSee('Diese Seite existiert nicht.')
            ->getContent();

        $this->assertSame('de', $this->documentLang($html));
    }

    public function test_a_signed_in_user_gets_the_unmatched_404_in_their_language_and_theme(): void
    {
        $user = User::factory()->create();
        $user->locale = Locale::De;
        $user->theme = Theme::Dark;
        $user->save();

        $html = $this->actingAs($user)
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Nicht gefunden')
            ->assertSee('Zurück zur Übersicht')
            ->getContent();

        $this->assertSame('de', $this->documentLang($html));
        $this->assertMatchesRegularExpression('/<html\b[^>]*\bdata-theme="dark"/', $html);
    }

    public function test_an_unmatched_write_is_a_404_not_a_405(): void
    {
        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->{$verb}('/no-such-page')->assertNotFound();
        }
    }
}
