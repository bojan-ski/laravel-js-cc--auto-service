<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function roleColumnProvider(): array
    {
        return [
            // role, columns: sku, name, stock, shelf (+ price, + cost)
            'mechanic' => ['mechanic', 4],
            'receptionist' => ['receptionist', 5],
            'admin' => ['admin', 6],
        ];
    }

    #[DataProvider('roleColumnProvider')]
    public function test_the_table_carries_exactly_the_columns_the_role_may_see(string $role, int $columns): void
    {
        Part::factory()->inStock()->create();

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame($columns, $this->countCells($this->tableHead($html), 'th'));
        $this->assertSame($columns, $this->countCells($this->firstBodyRow($html), 'td'));
    }

    #[DataProvider('roleColumnProvider')]
    public function test_every_header_matches_a_cell_below_md(string $role): void
    {
        Part::factory()->inStock()->create();

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            $this->countShownBelowMd($this->firstBodyRow($html)),
            $this->countShownBelowMd($this->tableHead($html)),
            "A {$role}'s header row exposes a different number of columns below md than its body row exposes cells."
        );
    }

    public function test_a_mechanic_sees_no_price_column_at_all(): void
    {
        Part::factory()->inStock()->create();

        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(__('parts.headers.price'), $this->tableHead($html));
        $this->assertStringNotContainsString(__('parts.headers.cost'), $this->tableHead($html));
    }

    public function test_a_receptionist_sees_price_but_no_cost_column(): void
    {
        Part::factory()->inStock()->create();

        $head = $this->tableHead($this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString(__('parts.headers.price'), $head);
        $this->assertStringNotContainsString(__('parts.headers.cost'), $head);
    }

    public function test_an_admin_sees_both_price_and_cost_columns(): void
    {
        Part::factory()->inStock()->create();

        $head = $this->tableHead($this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString(__('parts.headers.price'), $head);
        $this->assertStringContainsString(__('parts.headers.cost'), $head);
    }

    /**
     * Scoped to the row: "Low" is a substring of the "Low stock" filter tab, so
     * a page-wide assertion would pass without any marker being rendered.
     *
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function markerProvider(): array
    {
        return [
            //             factory state, shows "Low", shows "Negative"
            'healthy' => ['inStock', false, false],
            'low' => ['lowStock', true, false],
            'negative' => ['negativeStock', false, true],
        ];
    }

    #[DataProvider('markerProvider')]
    public function test_the_stock_cell_marks_low_and_negative_distinctly(
        string $state,
        bool $low,
        bool $negative,
    ): void {
        Part::factory()->{$state}()->create();

        $row = $this->firstBodyRow($this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent());

        $this->assertSame($low, str_contains($row, '>'.__('parts.stock.low').'<'));
        $this->assertSame($negative, str_contains($row, '>'.__('parts.stock.negative').'<'));
    }

    public function test_the_movements_section_renders_a_heading_and_empty_state_with_no_controls(): void
    {
        $part = Part::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee(__('parts.show.movements'))
            ->assertSee(__('parts.show.movements_empty'))
            ->getContent();

        $section = $this->movementsSection($html);

        $this->assertStringNotContainsString('<form', $section);
        $this->assertStringNotContainsString('<button', $section);
        $this->assertStringNotContainsString('<a ', $section);
    }

    public function test_the_delete_confirmation_warns_that_the_sku_stays_reserved(): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee(__('parts.delete.confirm', ['sku' => 'BP-2210']), false);
    }

    public function test_the_create_form_offers_no_stock_field_and_says_why(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.create'))
            ->assertOk()
            ->assertSee(__('parts.form.stock_note'))
            ->assertDontSee('name="quantity_in_stock"', false);
    }

    public function test_the_cost_price_field_is_offered_only_to_an_admin(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.create'))
            ->assertOk()
            ->assertDontSee('name="cost_price"', false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.create'))
            ->assertOk()
            ->assertSee('name="cost_price"', false);
    }

    public function test_the_edit_form_uses_the_put_spoof(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.edit', $part))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);
    }

    private function tableHead(string $html): string
    {
        preg_match('/<thead\b.*?<\/thead>/s', $html, $match);

        $this->assertNotEmpty($match, 'No <thead> was rendered.');

        return $match[0];
    }

    private function firstBodyRow(string $html): string
    {
        preg_match('/<tbody\b.*?<\/tbody>/s', $html, $body);

        $this->assertNotEmpty($body, 'No <tbody> was rendered.');

        preg_match('/<tr\b.*?<\/tr>/s', $body[0], $row);

        $this->assertNotEmpty($row, 'No body row was rendered.');

        return $row[0];
    }

    private function movementsSection(string $html): string
    {
        preg_match('/<section[^>]*>(?:(?!<\/section>).)*'.preg_quote(__('parts.show.movements'), '/').'.*?<\/section>/s', $html, $match);

        $this->assertNotEmpty($match, 'No movements section was rendered.');

        return $match[0];
    }

    private function countCells(string $markup, string $tag): int
    {
        preg_match_all('/<'.$tag.'\b[^>]*>/', $markup, $cells);

        return count($cells[0]);
    }

    private function countShownBelowMd(string $markup): int
    {
        preg_match_all('/<(?:th|td)\b[^>]*>/', $markup, $cells);

        return count(array_filter(
            $cells[0],
            fn (string $cell): bool => ! preg_match('/class="[^"]*\bhidden\b/', $cell),
        ));
    }
}
