<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartIdentifierSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function searchAs(string $term, ?User $user = null): string
    {
        return $this->actingAs($user ?? User::factory()->receptionist()->create())
            ->get(route('parts.index', ['search' => $term]))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function equivalentTermProvider(): array
    {
        return [
            'exact' => ['1K0615301AA'],
            'spaced' => ['1K0 615 301 AA'],
            'hyphenated' => ['1K0-615-301-AA'],
            'lower case' => ['1k0615301aa'],
            'lower and spaced' => ['1k0 615 301 aa'],
            'prefix only' => ['1K0615'],
        ];
    }

    #[DataProvider('equivalentTermProvider')]
    public function test_a_part_is_found_by_any_written_form_of_its_oe_number(string $term): void
    {
        $part = Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->oe()->number('1K0 615 301 AA')->create();

        Part::factory()->create(['sku' => 'ZZ-0002', 'name' => 'Unrelated']);

        $html = $this->searchAs($term);

        $this->assertStringContainsString('ZZ-0001', $html);
        $this->assertStringNotContainsString('ZZ-0002', $html);
    }

    public function test_searching_still_matches_the_sku(): void
    {
        Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        Part::factory()->create(['sku' => 'ZZ-0002', 'name' => 'Unrelated']);

        $html = $this->searchAs('ZZ-0001');

        $this->assertStringContainsString('ZZ-0001', $html);
        $this->assertStringNotContainsString('ZZ-0002', $html);
    }

    public function test_searching_still_matches_the_name(): void
    {
        Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        Part::factory()->create(['sku' => 'ZZ-0002', 'name' => 'Unrelated']);

        $html = $this->searchAs('Control arm');

        $this->assertStringContainsString('ZZ-0001', $html);
        $this->assertStringNotContainsString('ZZ-0002', $html);
    }

    public function test_a_catalogue_or_barcode_number_matches_too(): void
    {
        $part = Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->ean()->number('4013872123456')->create();

        $this->assertStringContainsString('ZZ-0001', $this->searchAs('4013872123456'));
    }

    /**
     * A term of pure punctuation normalizes to nothing. Without the guard the
     * identifier clause becomes LIKE '%' and every part matches.
     */
    public function test_a_term_with_no_alphanumerics_does_not_match_every_part(): void
    {
        $part = Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $html = $this->searchAs('---');

        $this->assertStringNotContainsString('ZZ-0001', $html);
    }

    public function test_a_trashed_parts_number_is_invisible_to_a_receptionist(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $this->assertStringNotContainsString('ZZ-0001', $this->searchAs('1K0615301AA'));
    }

    public function test_an_admin_finds_a_trashed_parts_number_with_the_trashed_filter(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['search' => '1K0 615 301 AA', 'trashed' => 'with']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ZZ-0001', $html);
    }

    public function test_an_admin_without_the_trashed_filter_does_not_find_it(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $html = $this->searchAs('1K0615301AA', User::factory()->admin()->create());

        $this->assertStringNotContainsString('ZZ-0001', $html);
    }

    public function test_a_number_on_another_part_does_not_drag_in_unrelated_rows(): void
    {
        $match = Part::factory()->create(['sku' => 'ZZ-0001', 'name' => 'Control arm']);
        PartNumber::factory()->for($match)->oe()->number('1K0615301AA')->create();

        $other = Part::factory()->create(['sku' => 'ZZ-0002', 'name' => 'Unrelated']);
        PartNumber::factory()->for($other)->oe()->number('8K0698451D')->create();

        $html = $this->searchAs('1K0615301AA');

        $this->assertStringContainsString('ZZ-0001', $html);
        $this->assertStringNotContainsString('ZZ-0002', $html);
    }

    /**
     * The low-stock tab counts must agree with the rows the same search shows,
     * so the identifier clause has to live inside the search group rather than
     * leaking out of it.
     */
    public function test_the_tab_counts_respect_an_identifier_search(): void
    {
        $match = Part::factory()->lowStock()->create(['sku' => 'ZZ-0001']);
        PartNumber::factory()->for($match)->oe()->number('1K0615301AA')->create();

        Part::factory()->count(4)->lowStock()->create();

        $html = $this->searchAs('1K0615301AA');

        $this->assertStringContainsString('ZZ-0001', $html);
        $this->assertSame(1, Part::query()->matching('1K0615301AA')->count());
    }
}
