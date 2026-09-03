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
}
