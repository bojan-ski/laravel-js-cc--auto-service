<?php

namespace Tests\Feature\Dashboard;

use App\Http\Requests\Parts\PartIndexRequest;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardLowStockTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function page(): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function part(string $name, int $inStock, int $reorderLevel): Part
    {
        return Part::factory()->create([
            'name' => $name,
            'quantity_in_stock' => $inStock,
            'reorder_level' => $reorderLevel,
        ]);
    }

    public function test_parts_at_or_below_the_reorder_level_are_listed_and_others_are_not(): void
    {
        $this->part('Timing belt kit', 9, 4);
        $this->part('Brake pads front', 4, 4);
        $this->part('Oil filter', 1, 4);
        $this->part('Wiper blade 24"', -2, 4);

        $page = $this->page();

        $page->assertSee('Brake pads front')->assertSee('Oil filter')->assertSee('Wiper blade 24"');
        $page->assertDontSee('Timing belt kit');
    }

    public function test_names_are_ordered_alphabetically(): void
    {
        $this->part('Wiper blade 24"', 1, 4);
        $this->part('Air filter', 1, 4);
        $this->part('Oil filter', 1, 4);

        $this->page()->assertSeeInOrder(['Air filter', 'Oil filter', 'Wiper blade 24&quot;'], false);
    }

    public function test_twenty_low_parts_show_twelve_names_and_a_count_of_twenty(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->part(sprintf('Filter %02d', $i), 1, 4);
        }

        $page = $this->page();

        $page->assertViewHas('parts', fn ($parts): bool => $parts->count() === 12);
        $page->assertViewHas('partCount', 20);
        $page->assertSee('Low stock (20)');

        $page->assertSee('Filter 01')->assertSee('Filter 12');
        $page->assertDontSee('Filter 13');
    }

    public function test_the_heading_count_is_the_true_total_not_the_name_cap(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->part(sprintf('Filter %02d', $i), 1, 4);
        }

        $this->page()
            ->assertSee('Low stock (15)')
            ->assertDontSee('Low stock (12)');
    }

    public function test_a_part_at_or_below_zero_is_distinguished_from_one_merely_low(): void
    {
        $this->part('Oil filter', 1, 4);
        $this->part('Spark plug', 0, 4);
        $this->part('Wiper blade 20"', -2, 4);

        $html = $this->page()->getContent();

        $this->assertSame(
            2,
            substr_count($html, __('dashboard.low_stock.out_of_stock')),
            'Only the zero and negative parts should be marked out of stock.',
        );
    }

    public function test_a_merely_low_part_carries_no_out_of_stock_marker(): void
    {
        $this->part('Oil filter', 1, 4);

        $this->page()->assertDontSee(__('dashboard.low_stock.out_of_stock'));
    }

    public function test_each_name_links_to_its_part(): void
    {
        $part = $this->part('Oil filter', 1, 4);

        $this->page()->assertSee(route('parts.show', $part));
    }

    public function test_the_panel_links_to_the_catalogue_filtered_to_low_stock(): void
    {
        $this->page()->assertSee(route('parts.index', ['filter' => PartIndexRequest::LOW_STOCK]));
    }

    /**
     * `PartFactory::definition()` leaves stock and reorder level both at 0, so a
     * default part is ALREADY low stock. This emptiness assertion is only
     * meaningful against parts built with an explicit healthy stock level.
     */
    public function test_the_panel_states_its_emptiness_when_nothing_needs_reordering(): void
    {
        Part::factory()->count(3)->inStock()->create();

        $this->page()->assertSee(__('dashboard.low_stock.empty'));
    }

    public function test_a_default_factory_part_is_low_stock(): void
    {
        $part = Part::factory()->create();

        $this->assertTrue($part->isLowStock(), 'The factory default changed; the emptiness test above is now vacuous.');
    }

    /**
     * The list and the heading count are two independently written queries. The
     * count is asserted here as well, because a `withTrashed()` added to one and
     * not the other would render "Low stock (1)" above "Nothing to reorder."
     */
    public function test_soft_deleted_parts_are_never_listed_or_counted(): void
    {
        $this->part('Oil filter', 1, 4)->delete();

        $this->page()
            ->assertDontSee('Oil filter')
            ->assertViewHas('partCount', 0)
            ->assertSee(__('dashboard.low_stock.empty'));
    }

    /**
     * Documents the intent only. Measured: dropping `orderBy('id')` leaves this
     * green, because MySQL returns primary-key order here regardless. The
     * assertion that actually fails on that mutation is
     * DashboardQueryTest::test_every_panel_query_carries_a_deterministic_tiebreak.
     */
    public function test_parts_with_the_same_name_are_ordered_by_id(): void
    {
        $first = $this->part('Oil filter', 1, 4);
        $second = $this->part('Oil filter', 1, 4);

        $this->page()->assertSeeInOrder([
            route('parts.show', $first).'"',
            route('parts.show', $second).'"',
        ], false);
    }
}
