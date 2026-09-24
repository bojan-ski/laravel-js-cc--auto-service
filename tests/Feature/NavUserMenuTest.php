<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * `data-dropdown` is the hook the dropdown module uses to close a menu on
     * an outside click or Escape. Both menus need it, desktop and mobile.
     */
    public function test_both_menus_are_hooked_for_outside_click_dismissal(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/')->getContent();

        preg_match_all('/<details\b[^>]*>/', $html, $disclosures);

        $this->assertCount(2, $disclosures[0]);

        foreach ($disclosures[0] as $disclosure) {
            $this->assertStringContainsString('data-dropdown', $disclosure);
        }
    }

    public function test_the_disclosure_does_not_override_the_visible_name_with_an_aria_label(): void
    {
        $user = User::factory()->create(['name' => 'Dragan Jovanovic']);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee('aria-label="'.__('app.nav.user_menu').'"', false);
        $response->assertSee('<span class="sr-only">'.__('app.nav.user_menu').'</span>', false);
    }

    /**
     * The user menu is rendered TWICE — a desktop `<details>` and a mobile one —
     * so a link added to only one of them is invisible at half the viewports.
     * Both assertions count occurrences rather than merely finding one.
     */
    public function test_an_admin_gets_a_staff_link_in_both_user_menus(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($html, 'href="'.route('admin.users.index').'"'),
            'The Staff link must appear in the desktop menu AND the mobile menu.'
        );

        $this->assertSame(2, substr_count($html, __('app.nav.staff')));
    }

    public function test_a_receptionist_gets_a_staff_link_in_neither_user_menu(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($html, 'href="'.route('admin.users.index').'"'));
        $this->assertSame(0, substr_count($html, __('app.nav.staff')));
    }

    public function test_a_mechanic_gets_a_staff_link_in_neither_user_menu(): void
    {
        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($html, 'href="'.route('admin.users.index').'"'));
        $this->assertSame(0, substr_count($html, __('app.nav.staff')));
    }

    /**
     * Scoped to the <header>, because the staff page's own filter tabs also link
     * to that URL and also carry aria-current. Matched as whole anchor tags,
     * because Blade puts a newline between `href` and the conditional attribute.
     *
     * @return list<string>
     */
    private function headerStaffLinks(string $html): array
    {
        preg_match('/<header\b.*?<\/header>/s', $html, $header);

        $this->assertNotEmpty($header, 'No header rendered.');

        preg_match_all(
            '/<a\b[^>]*href="'.preg_quote(route('admin.users.index'), '/').'"[^>]*>/s',
            $header[0],
            $links
        );

        return $links[0];
    }

    public function test_the_staff_link_is_marked_current_while_in_the_staff_section(): void
    {
        $admin = User::factory()->admin()->create();

        $onStaff = $this->headerStaffLinks(
            $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent()
        );

        $this->assertCount(2, $onStaff, 'Expected a desktop and a mobile Staff link.');

        foreach ($onStaff as $link) {
            $this->assertStringContainsString('aria-current="page"', $link);
        }

        $elsewhere = $this->headerStaffLinks(
            $this->actingAs($admin)->get('/')->assertOk()->getContent()
        );

        $this->assertCount(2, $elsewhere);

        foreach ($elsewhere as $link) {
            $this->assertStringNotContainsString(
                'aria-current="page"',
                $link,
                'The Staff links must not claim to be the current page from the dashboard.'
            );
        }
    }

    /**
     * The entry lives in the two user menus only. Putting it in the primary strip
     * as well would place an admin-only destination in front of every mechanic.
     * Asserted against the two `<nav>` blocks rather than a page-wide count,
     * because the staff page's own filter tabs legitimately link to the same URL.
     */
    public function test_the_staff_link_is_not_added_to_the_primary_navigation(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        preg_match_all(
            '/<nav[^>]*aria-label="'.preg_quote(__('app.nav.primary'), '/').'"[^>]*>.*?<\/nav>/s',
            $html,
            $strips
        );

        $this->assertCount(2, $strips[0], 'Expected a desktop and a mobile primary strip.');

        foreach ($strips[0] as $strip) {
            $this->assertStringNotContainsString(route('admin.users.index'), $strip);
            $this->assertStringNotContainsString(__('app.nav.staff'), $strip);
        }
    }

    public function test_the_menu_carries_a_csrf_token(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('name="_token"', false);
    }

    /**
     * Scoped to the <header> and matched per form, because the menu renders
     * twice and a switch added to only one menu is invisible at half the
     * viewports.
     *
     * @return list<string>
     */
    private function headerThemeForms(string $html): array
    {
        preg_match('/<header\b.*?<\/header>/s', $html, $header);

        $this->assertNotEmpty($header, 'No header rendered.');

        preg_match_all(
            '/<form\b[^>]*action="'.preg_quote(route('preferences.theme.update'), '/').'"[^>]*>.*?<\/form>/s',
            $header[0],
            $forms
        );

        return $forms[0];
    }

    public function test_both_user_menus_offer_every_theme_through_a_patch_form(): void
    {
        $forms = $this->headerThemeForms(
            $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent()
        );

        $this->assertCount(2, $forms, 'Expected a desktop and a mobile theme switch.');

        foreach ($forms as $form) {
            $this->assertStringContainsString('name="_method" value="PATCH"', $form);
            $this->assertStringContainsString('name="_token"', $form);

            foreach (Theme::cases() as $theme) {
                $this->assertMatchesRegularExpression(
                    '/<button\b[^>]*name="theme"[^>]*value="'.$theme->value.'"[^>]*>\s*(<span aria-hidden="true">[^<]*<\/span>\s*)?'.preg_quote($theme->label(), '/').'\s*<\/button>/',
                    $form,
                );
            }
        }
    }

    #[DataProvider('themeProvider')]
    public function test_only_the_saved_theme_is_marked_pressed(Theme $saved): void
    {
        $user = User::factory()->create();
        $user->theme = $saved;
        $user->save();

        $forms = $this->headerThemeForms($this->actingAs($user)->get('/')->assertOk()->getContent());

        $this->assertCount(2, $forms);

        foreach ($forms as $form) {
            preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*aria-pressed="(true|false)"/', $form, $buttons, PREG_SET_ORDER);

            $pressed = array_column($buttons, 2, 1);

            $this->assertSame(
                array_combine(
                    array_map(fn (Theme $theme): string => $theme->value, Theme::cases()),
                    array_map(fn (Theme $theme): string => $theme === $saved ? 'true' : 'false', Theme::cases()),
                ),
                $pressed,
            );
        }
    }

    /**
     * The saved option must be told apart without relying on colour alone:
     * hovering any other option gives it the same tint and text colour, so the
     * current one keeps a visible border and heavier text that hover never
     * applies. (A check mark was tried and removed on the user's ruling — the
     * border is the chosen cue.) `aria-pressed` reports the state to
     * assistive technology.
     */
    #[DataProvider('themeProvider')]
    public function test_the_saved_theme_carries_a_non_colour_marker(Theme $saved): void
    {
        $user = User::factory()->create();
        $user->theme = $saved;
        $user->save();

        $forms = $this->headerThemeForms($this->actingAs($user)->get('/')->assertOk()->getContent());

        $this->assertCount(2, $forms);

        foreach ($forms as $form) {
            preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*>(.*?)<\/button>/s', $form, $buttons, PREG_SET_ORDER);

            $this->assertCount(count(Theme::cases()), $buttons);

            foreach ($buttons as [$tag, $value]) {
                $isSaved = $value === $saved->value;

                $this->assertSame($isSaved, str_contains($tag, 'border-text-inverse/60'), "Only the saved option carries the visible border ({$value}).");
                $this->assertSame(! $isSaved, str_contains($tag, 'border-transparent'), "Every other option's border is transparent ({$value}).");
                $this->assertSame($isSaved, str_contains($tag, 'font-semibold'));
                $this->assertStringNotContainsString('hover:border', $tag, 'Hover must never add the border that marks the saved option.');
            }
        }
    }

    /**
     * Each switch is a labelled group, and no two of the four copies (theme
     * and language, desktop and mobile) may share an id — a duplicate would
     * label both groups from whichever came first.
     */
    public function test_each_preference_switch_is_a_group_labelled_by_its_own_heading(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

        $labelIds = [];

        foreach ([[$this->headerThemeForms($html), 'app.theme.label'], [$this->headerLocaleForms($html), 'app.language.label']] as [$forms, $labelKey]) {
            $this->assertCount(2, $forms);

            foreach ($forms as $form) {
                $this->assertMatchesRegularExpression('/role="group" aria-labelledby="([^"]+)"/', $form);
                preg_match('/aria-labelledby="([^"]+)"/', $form, $labelledBy);
                $this->assertMatchesRegularExpression(
                    '/id="'.preg_quote($labelledBy[1], '/').'"[^>]*>\s*'.preg_quote(__($labelKey), '/').'\s*</',
                    $form,
                );
                $labelIds[] = $labelledBy[1];
            }
        }

        $this->assertCount(4, array_unique($labelIds));
    }

    /**
     * @return list<string>
     */
    private function headerLocaleForms(string $html): array
    {
        preg_match('/<header\b.*?<\/header>/s', $html, $header);

        $this->assertNotEmpty($header, 'No header rendered.');

        preg_match_all(
            '/<form\b[^>]*action="'.preg_quote(route('preferences.locale.update'), '/').'"[^>]*>.*?<\/form>/s',
            $header[0],
            $forms
        );

        return $forms[0];
    }

    public function test_both_user_menus_offer_every_language_by_its_own_name(): void
    {
        $forms = $this->headerLocaleForms(
            $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent()
        );

        $this->assertCount(2, $forms, 'Expected a desktop and a mobile language switch.');

        foreach ($forms as $form) {
            $this->assertStringContainsString('name="_method" value="PATCH"', $form);

            foreach (['en' => 'English', 'sr' => 'Srpski', 'ro' => 'Română', 'de' => 'Deutsch'] as $value => $name) {
                $this->assertMatchesRegularExpression(
                    '/<button\b[^>]*name="locale"[^>]*value="'.$value.'"[^>]*>\s*(<span aria-hidden="true">[^<]*<\/span>\s*)?'.preg_quote($name, '/').'\s*<\/button>/u',
                    $form,
                );
            }
        }
    }

    /**
     * Each language is named in itself, so each name is in a language other
     * than the page's; without its own `lang` a screen reader pronounces
     * "Deutsch" with Serbian rules (WCAG 3.1.2). Theme options are words in
     * the page's own language and must not claim another one.
     */
    public function test_each_language_name_declares_its_own_language(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

        foreach ($this->headerLocaleForms($html) as $form) {
            preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*\blang="([^"]+)"/', $form, $buttons, PREG_SET_ORDER);

            $this->assertSame(['en' => 'en', 'sr' => 'sr-Latn', 'ro' => 'ro', 'de' => 'de'], array_column($buttons, 2, 1));
        }

        foreach ($this->headerThemeForms($html) as $form) {
            $this->assertDoesNotMatchRegularExpression('/<button\b[^>]*\blang=/', $form);
        }
    }

    /**
     * A user who never chose is shown the language actually in use (here the
     * shop default) as the pressed one, so the menu never claims no language.
     */
    public function test_the_language_in_use_is_marked_pressed(): void
    {
        config(['shop.locale' => 'ro']);

        $forms = $this->headerLocaleForms(
            $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent()
        );

        foreach ($forms as $form) {
            preg_match_all('/<button\b[^>]*value="([a-z]+)"[^>]*aria-pressed="(true|false)"/', $form, $buttons, PREG_SET_ORDER);

            $this->assertSame(['en' => 'false', 'sr' => 'false', 'ro' => 'true', 'de' => 'false'], array_column($buttons, 2, 1));
        }
    }

    /**
     * @return array<string, array{0: Theme}>
     */
    public static function themeProvider(): array
    {
        return array_combine(
            array_map(fn (Theme $theme): string => $theme->value, Theme::cases()),
            array_map(fn (Theme $theme): array => [$theme], Theme::cases()),
        );
    }
}
