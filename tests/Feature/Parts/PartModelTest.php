<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: int, 1: int, 2: bool, 3: bool, 4: bool}>
     */
    public static function stockProvider(): array
    {
        return [
            //                    stock, reorder, low,   out,   negative
            'well stocked' => [12, 4, false, false, false],
            'one above the level' => [5, 4, false, false, false],
            'exactly at the level' => [4, 4, true, false, false],
            'below the level' => [1, 4, true, false, false],
            'zero with a level' => [0, 4, true, true, false],
            'zero with no level' => [0, 0, true, true, false],
            'negative' => [-2, 4, true, true, true],
            'negative with no level' => [-2, 0, true, true, true],
        ];
    }

    #[DataProvider('stockProvider')]
    public function test_the_stock_predicates_agree_with_the_contract(
        int $stock,
        int $reorder,
        bool $low,
        bool $out,
        bool $negative,
    ): void {
        $part = Part::factory()->make(['quantity_in_stock' => $stock, 'reorder_level' => $reorder]);

        $this->assertSame($low, $part->isLowStock());
        $this->assertSame($out, $part->isOutOfStock());
        $this->assertSame($negative, $part->hasNegativeStock());
    }

    public function test_the_low_stock_scope_returns_exactly_the_parts_at_or_below_their_level(): void
    {
        $healthy = Part::factory()->inStock()->create();
        $atLevel = Part::factory()->create(['quantity_in_stock' => 4, 'reorder_level' => 4]);
        $low = Part::factory()->lowStock()->create();
        $negative = Part::factory()->negativeStock()->create();

        $found = Part::query()->lowStock()->pluck('id');

        $this->assertEqualsCanonicalizing(
            [$atLevel->getKey(), $low->getKey(), $negative->getKey()],
            $found->all(),
        );

        $this->assertNotContains($healthy->getKey(), $found->all());
    }

    public function test_matching_searches_both_sku_and_name(): void
    {
        $bySku = Part::factory()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);
        $byName = Part::factory()->create(['sku' => 'ZZ-9999', 'name' => 'Brake pads front']);

        $this->assertSame([$bySku->getKey()], Part::query()->matching('1042')->pluck('id')->all());
        $this->assertSame([$byName->getKey()], Part::query()->matching('brake')->pluck('id')->all());
    }

    public function test_matching_escapes_like_wildcards(): void
    {
        Part::factory()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);

        $this->assertSame(0, Part::query()->matching('%')->count());
        $this->assertSame(0, Part::query()->matching('_F-1042')->count());
    }

    public function test_stock_is_not_mass_assignable(): void
    {
        $part = Part::query()->create([
            'sku' => 'OF-2000',
            'name' => 'Oil filter',
            'unit_price' => '850.00',
            'reorder_level' => 4,
            'quantity_in_stock' => 99,
        ]);

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
    }
}
