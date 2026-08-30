<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PartCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->receptionist()->create();
    }

    public function test_parts_are_listed_in_name_order(): void
    {
        Part::factory()->create(['name' => 'Wiper blade 24"', 'sku' => 'WB-24']);
        Part::factory()->create(['name' => 'Air filter', 'sku' => 'AF-01']);
        Part::factory()->create(['name' => 'Oil filter', 'sku' => 'OF-01']);

        $this->actingAs($this->receptionist())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertSeeInOrder(['Air filter', 'Oil filter', 'Wiper blade 24&quot;'], false);
    }

    public function test_the_catalogue_paginates_at_twenty_five(): void
    {
        Part::factory()->count(26)->create();

        $response = $this->actingAs($this->receptionist())->get(route('parts.index'))->assertOk();

        $this->assertSame(25, $response->viewData('parts')->count());
        $this->assertSame(26, $response->viewData('parts')->total());
    }

    public function test_the_low_stock_tab_url_preserves_the_current_search_term(): void
    {
        Part::factory()->lowStock()->create(['name' => 'Oil filter']);

        $html = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'Oil']))
            ->assertOk()
            ->getContent();

        $href = $this->linkHref($html, __('parts.tabs.low_stock'));

        $this->assertStringContainsString('filter=low_stock', $href);
        $this->assertStringContainsString('search=Oil', $href);
    }

    public function test_the_active_tab_is_the_only_element_marked_as_the_current_page(): void
    {
        Part::factory()->lowStock()->create();

        $html = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['filter' => 'low_stock']))
            ->assertOk()
            ->getContent();

        preg_match('/<nav[^>]*aria-label="'.preg_quote(__('parts.tabs.label'), '/').'".*?<\/nav>/s', $html, $nav);

        $this->assertNotEmpty($nav, 'The filter tab navigation was not rendered.');
        $this->assertSame(1, substr_count($nav[0], 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>\s*'.preg_quote(__('parts.tabs.low_stock'), '/').'/s',
            preg_replace('/<a\s+href="[^"]*"\s*/', '<a ', $nav[0]) ?? '',
        );
    }

    public function test_the_search_form_carries_the_active_filters_as_hidden_inputs(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['filter' => 'low_stock', 'trashed' => 'with']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="filter" value="low_stock">', false)
            ->assertSee('<input type="hidden" name="trashed" value="with">', false);
    }

    public function test_the_search_form_carries_no_hidden_inputs_when_no_filter_is_active(): void
    {
        $this->actingAs($this->receptionist())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertDontSee('name="filter"', false)
            ->assertDontSee('name="trashed"', false);
    }

    private function linkHref(string $html, string $label): string
    {
        $pattern = '/<a\s[^>]*href="([^"]+)"[^>]*>\s*'.preg_quote($label, '/').'\s*</s';

        $this->assertMatchesRegularExpression($pattern, $html, "No link labelled \"{$label}\" was rendered.");

        preg_match($pattern, $html, $match);

        return html_entity_decode($match[1]);
    }

    public function test_search_matches_a_partial_sku_case_insensitively(): void
    {
        Part::factory()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);
        Part::factory()->create(['sku' => 'BP-2210', 'name' => 'Brake pads front']);

        $response = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'of-10']))
            ->assertOk();

        $this->assertSame(['OF-1042'], $response->viewData('parts')->pluck('sku')->all());
    }

    public function test_search_matches_a_partial_name_case_insensitively(): void
    {
        Part::factory()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);
        Part::factory()->create(['sku' => 'BP-2210', 'name' => 'Brake pads front']);

        $response = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'BRAKE']))
            ->assertOk();

        $this->assertSame(['BP-2210'], $response->viewData('parts')->pluck('sku')->all());
    }

    public function test_the_low_stock_filter_returns_exactly_the_parts_at_or_below_their_level(): void
    {
        Part::factory()->inStock()->create(['sku' => 'HEALTHY-1']);
        Part::factory()->lowStock()->create(['sku' => 'LOW-1']);
        Part::factory()->negativeStock()->create(['sku' => 'NEG-1']);
        Part::factory()->create(['sku' => 'AT-LEVEL-1', 'quantity_in_stock' => 4, 'reorder_level' => 4]);

        $response = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['filter' => 'low_stock']))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['LOW-1', 'NEG-1', 'AT-LEVEL-1'],
            $response->viewData('parts')->pluck('sku')->all(),
        );
    }

    public function test_the_tab_counts_respect_the_search_term(): void
    {
        Part::factory()->inStock()->create(['name' => 'Oil filter']);
        Part::factory()->lowStock()->create(['name' => 'Oil filter premium']);
        Part::factory()->lowStock()->create(['name' => 'Brake pads front']);

        $response = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'Oil']))
            ->assertOk();

        $this->assertSame(['all' => 2, 'low_stock' => 1], $response->viewData('counts'));
    }

    public function test_the_low_stock_count_ignores_the_active_filter(): void
    {
        Part::factory()->inStock()->count(2)->create();
        Part::factory()->lowStock()->create();

        $response = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['filter' => 'low_stock']))
            ->assertOk();

        $this->assertSame(['all' => 3, 'low_stock' => 1], $response->viewData('counts'));
    }

    public function test_filters_and_search_survive_pagination(): void
    {
        Part::factory()->count(30)->lowStock()->create(['name' => 'Oil filter']);

        $links = $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'Oil', 'filter' => 'low_stock']))
            ->assertOk()
            ->viewData('parts')
            ->appends([])
            ->nextPageUrl();

        $this->assertStringContainsString('search=Oil', $links);
        $this->assertStringContainsString('filter=low_stock', $links);
        $this->assertStringContainsString('page=2', $links);
    }

    public function test_an_invalid_filter_value_is_rejected(): void
    {
        $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['filter' => 'nonsense']))
            ->assertSessionHasErrors('filter');
    }

    public function test_the_empty_catalogue_offers_the_create_action(): void
    {
        $this->actingAs($this->receptionist())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertSee(__('parts.empty.none.title'))
            ->assertSee(route('parts.create'), false);
    }

    public function test_a_search_with_no_matches_offers_clearing_the_search(): void
    {
        Part::factory()->create(['name' => 'Oil filter']);

        $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['search' => 'nothing matches this']))
            ->assertOk()
            ->assertSee(__('parts.empty.no_matches.title'))
            ->assertDontSee(__('parts.empty.none.title'));
    }

    public function test_a_low_stock_filter_with_no_matches_offers_showing_all_parts(): void
    {
        Part::factory()->inStock()->create();

        $this->actingAs($this->receptionist())
            ->get(route('parts.index', ['filter' => 'low_stock']))
            ->assertOk()
            ->assertSee(__('parts.empty.none_low.title'))
            ->assertDontSee(__('parts.empty.none.title'))
            ->assertDontSee(__('parts.empty.no_matches.title'));
    }
}
