<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'name' => 'Aleksandar Admin',
            'email' => 'aleks@shop.rs',
        ]);
    }

    public function test_offboarded_accounts_are_listed_by_default(): void
    {
        $admin = $this->admin();
        $gone = User::factory()->mechanic()->trashed()->create(['name' => 'Nikola Stankovic']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Nikola Stankovic')
            ->assertSee(__('staff.offboarded.on', ['date' => $gone->deleted_at->format('j M Y')]));
    }

    public function test_search_matches_a_partial_name_including_offboarded_accounts(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->trashed()->create(['name' => 'Dragan Jovanovic', 'email' => 'dragan@shop.rs']);
        User::factory()->mechanic()->create(['name' => 'Milan Petrov', 'email' => 'milan@shop.rs']);

        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'jovan']))
            ->assertOk()
            ->assertSee('Dragan Jovanovic')
            ->assertDontSee('Milan Petrov');
    }

    public function test_search_matches_a_partial_email(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic', 'email' => 'dragan@shop.rs']);
        User::factory()->mechanic()->create(['name' => 'Milan Petrov', 'email' => 'milan@shop.rs']);

        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'milan@']))
            ->assertOk()
            ->assertSee('Milan Petrov')
            ->assertDontSee('Dragan Jovanovic');
    }

    public function test_the_role_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->create(['name' => 'Mechanic Person']);
        User::factory()->receptionist()->create(['name' => 'Reception Person']);

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'mechanic']))
            ->assertOk()
            ->assertSee('Mechanic Person')
            ->assertDontSee('Reception Person');
    }

    public function test_an_unknown_role_is_refused_rather_than_ignored(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['role' => 'owner']))
            ->assertRedirect()
            ->assertSessionHasErrors('role');
    }

    public function test_filters_survive_pagination(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->mechanic()->create(['name' => 'Zeljko Tester']);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'search' => 'Zeljko',
            'role' => 'mechanic',
            'page' => 2,
        ]))->assertOk();

        $members = $response->viewData('members');

        $this->assertSame(2, $members->currentPage());
        $this->assertSame(30, $members->total());
        $this->assertCount(5, $members);

        $response->assertSee('search=Zeljko', false);
        $response->assertSee('role=mechanic', false);
    }

    public function test_the_list_is_ordered_by_name(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Zzz Last']);
        User::factory()->mechanic()->create(['name' => 'Aaa First']);
        User::factory()->receptionist()->create(['name' => 'Mmm Middle']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $this->assertSame(
            ['Aaa First', 'Mmm Middle', 'Zzz Last'],
            $response->viewData('members')->pluck('name')->all()
        );
    }

    /**
     * The literal 25 is deliberate: reading StaffService::PER_PAGE here would
     * make the assertion agree with any value the constant is changed to.
     */
    public function test_a_page_holds_twenty_five_rows(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->mechanic()->create();

        $members = $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->viewData('members');

        $this->assertCount(25, $members);
        $this->assertSame(31, $members->total());
    }

    public function test_the_tab_counts_respect_the_search_term_and_all_equals_their_sum(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Match Admin']);
        User::factory()->count(3)->mechanic()->create(['name' => 'Match Mechanic']);
        User::factory()->count(2)->receptionist()->create(['name' => 'Match Reception']);
        User::factory()->count(4)->mechanic()->create(['name' => 'Other Person']);

        $counts = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Match']))
            ->assertOk()
            ->viewData('counts');

        $this->assertSame(3, $counts['roles']['mechanic']);
        $this->assertSame(2, $counts['roles']['receptionist']);
        $this->assertSame(1, $counts['roles']['admin']);
        $this->assertSame(6, $counts['all']);
        $this->assertSame(array_sum($counts['roles']), $counts['all']);
    }

    public function test_the_tabs_render_a_count_each_role_can_be_read_by_assistive_technology(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->mechanic()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(trans_choice('staff.count', 3, ['count' => 3]))
            ->assertSee(trans_choice('staff.count', 2, ['count' => 2]));
    }

    /**
     * A bare page-wide `aria-current` presence check cannot tell a correct active
     * tab from one that marks every tab, or always marks All. Both the count and
     * the marked tab's own URL are asserted.
     */
    public function test_exactly_one_tab_is_marked_current_and_it_is_the_filtered_one(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->create();

        $html = $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'mechanic']))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            route('admin.users.index', ['role' => 'mechanic']),
            $this->currentTabHref($html)
        );
    }

    public function test_the_all_tab_is_the_current_one_when_no_role_is_filtered(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(route('admin.users.index'), $this->currentTabHref($html));
    }

    public function test_a_role_tab_preserves_the_active_search_term(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->create(['name' => 'Match Mechanic']);

        $html = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Match']))
            ->assertOk()
            ->getContent();

        $this->assertContains(
            route('admin.users.index', ['search' => 'Match', 'role' => 'mechanic']),
            $this->tabHrefs($html)
        );
    }

    public function test_clearing_the_search_preserves_the_role_filter(): void
    {
        $admin = $this->admin();
        User::factory()->mechanic()->create(['name' => 'Match Mechanic']);

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Match', 'role' => 'mechanic']))
            ->assertOk()
            ->getContent();

        preg_match('/<form[^>]*role="search".*?<\/form>/s', $html, $form);

        $this->assertNotEmpty($form, 'No search form rendered.');
        $this->assertStringContainsString(__('app.actions.clear_search'), $form[0]);
        $this->assertContains(
            route('admin.users.index', ['role' => 'mechanic']),
            $this->decodedHrefs($form[0]),
            'Clearing the search must keep the active role filter.'
        );
    }

    /**
     * Scoped to the filter-tabs <nav>: the two user-menu Staff links also carry
     * aria-current on this page, so a page-wide count cannot isolate the tabs.
     * Hrefs are decoded because Blade escapes `&` to `&amp;` in an attribute.
     */
    private function filterTabs(string $html): string
    {
        $matched = preg_match(
            '/<nav[^>]*aria-label="'.preg_quote(__('staff.tabs.label'), '/').'"[^>]*>.*?<\/nav>/s',
            $html,
            $nav
        );

        $this->assertSame(1, $matched, 'No filter-tabs nav rendered.');

        return $nav[0];
    }

    private function currentTabHref(string $html): string
    {
        $tabs = $this->filterTabs($html);

        $this->assertSame(1, substr_count($tabs, 'aria-current="page"'), 'Exactly one tab must be current.');

        preg_match('/<a\b[^>]*?href="([^"]+)"[^>]*?aria-current="page"/s', $tabs, $found);

        return html_entity_decode($found[1]);
    }

    /**
     * @return list<string>
     */
    private function tabHrefs(string $html): array
    {
        return $this->decodedHrefs($this->filterTabs($html));
    }

    /**
     * @return list<string>
     */
    private function decodedHrefs(string $markup): array
    {
        preg_match_all('/href="([^"]+)"/', $markup, $hrefs);

        return array_map(html_entity_decode(...), $hrefs[1]);
    }

    public function test_a_search_with_no_matches_shows_the_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['search' => 'nobody-by-that-name']))
            ->assertOk()
            ->assertSee(__('staff.empty.title'))
            ->assertSee(__('staff.empty.clear'));
    }

    public function test_a_role_filter_with_no_matches_shows_the_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.users.index', ['role' => 'mechanic']))
            ->assertOk()
            ->assertSee(__('staff.empty.title'));
    }

    public function test_a_full_page_issues_a_bounded_number_of_queries(): void
    {
        User::factory()->count(20)->mechanic()->trashed()->create();
        User::factory()->count(10)->receptionist()->create();
        $admin = $this->admin();

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()->assertViewHas('members', fn ($page): bool => $page->count() === 25);

        $this->assertCount(3, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_the_row_count_does_not_change_the_query_count(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->mechanic()->create();

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        User::factory()->count(22)->mechanic()->create();
        DB::flushQueryLog();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large, 'The staff list issues a query per row.');
    }
}
