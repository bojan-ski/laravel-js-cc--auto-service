<?php

namespace Tests\Feature\Preferences;

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Which language a request renders in: the user's saved choice, else the
 * choice made on the login page, else the shop default.
 */
class SetLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function userSpeaking(?Locale $locale): User
    {
        $user = User::factory()->create();
        $user->locale = $locale;
        $user->save();

        return $user;
    }

    private function documentLang(string $html): ?string
    {
        preg_match('/<html\b[^>]*\blang="([^"]*)"/', $html, $match);

        return $match[1] ?? null;
    }

    /**
     * Asserts the page is in $locale by a string that reads differently in
     * English, so a fallback to English cannot pass.
     */
    private function assertRenderedIn(Locale $locale, string $html): void
    {
        $expected = __('app.nav.sign_out', [], $locale->value);

        if ($locale !== Locale::En) {
            $this->assertNotSame(__('app.nav.sign_out', [], 'en'), $expected, "{$locale->value} has no translation of the probe string.");
        }

        $this->assertStringContainsString(e($expected), $html);
        $this->assertSame($locale->htmlLang(), $this->documentLang($html));
    }

    public function test_a_users_saved_language_renders_the_page(): void
    {
        $html = $this->actingAs($this->userSpeaking(Locale::De))->get('/')->assertOk()->getContent();

        $this->assertRenderedIn(Locale::De, $html);
    }

    public function test_serbian_is_marked_as_latin_script(): void
    {
        $html = $this->actingAs($this->userSpeaking(Locale::Sr))->get('/')->assertOk()->getContent();

        $this->assertSame('sr-Latn', $this->documentLang($html));
        $this->assertRenderedIn(Locale::Sr, $html);
    }

    public function test_the_saved_language_wins_over_the_login_page_choice(): void
    {
        $html = $this->actingAs($this->userSpeaking(Locale::Ro))
            ->withSession([SetLocale::SESSION_KEY => 'de'])
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertRenderedIn(Locale::Ro, $html);
    }

    public function test_a_user_who_never_chose_keeps_the_login_page_choice(): void
    {
        $html = $this->actingAs($this->userSpeaking(null))
            ->withSession([SetLocale::SESSION_KEY => 'ro'])
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertRenderedIn(Locale::Ro, $html);
    }

    public function test_otherwise_the_shop_default_applies(): void
    {
        config(['shop.locale' => 'de']);

        $html = $this->actingAs($this->userSpeaking(null))->get('/')->assertOk()->getContent();

        $this->assertRenderedIn(Locale::De, $html);
    }

    public function test_an_unknown_session_value_is_ignored(): void
    {
        config(['shop.locale' => 'de']);

        $html = $this->actingAs($this->userSpeaking(null))
            ->withSession([SetLocale::SESSION_KEY => '../../etc/passwd'])
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertRenderedIn(Locale::De, $html);
    }

    public function test_the_default_is_english(): void
    {
        $html = $this->actingAs($this->userSpeaking(null))->get('/')->assertOk()->getContent();

        $this->assertRenderedIn(Locale::En, $html);
    }

    /**
     * Month names come from Carbon, which follows the app locale through its
     * own LocaleUpdated listener rather than through the translator.
     */
    public function test_dates_are_written_in_the_users_language(): void
    {
        $this->travelTo('2026-10-15 10:00');

        Job::factory()->create();

        $german = now()->locale('de')->isoFormat('D MMM');

        $this->assertNotSame(now()->locale('en')->isoFormat('D MMM'), $german);

        $this->actingAs($this->userSpeaking(Locale::De))
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee($german);
    }

    public function test_a_guest_page_renders_in_the_session_language(): void
    {
        $html = $this->withSession([SetLocale::SESSION_KEY => 'de'])->get(route('login'))->assertOk()->getContent();

        $this->assertSame('de', $this->documentLang($html));
        $this->assertStringContainsString(e(__('app.language.label', [], 'de')), $html);
    }
}
