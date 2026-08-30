<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PRICE = '4200.00';

    private const COST = '2850.00';

    private function part(): Part
    {
        return Part::factory()->inStock()->create([
            'sku' => 'BP-2210',
            'name' => 'Brake pads front',
            'unit_price' => self::PRICE,
            'cost_price' => self::COST,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function tierProvider(): array
    {
        return [
            //                 role,          sees unit_price, sees cost_price
            'mechanic' => ['mechanic', false, false],
            'receptionist' => ['receptionist', true, false],
            'admin' => ['admin', true, true],
        ];
    }

    #[DataProvider('tierProvider')]
    public function test_the_scope_selects_exactly_the_columns_the_tier_may_see(
        string $role,
        bool $seesPrice,
        bool $seesCost,
    ): void {
        $this->part();
        $user = User::factory()->{$role}()->create();

        $part = Part::query()->visibleTo($user)->sole();

        $this->assertSame($seesPrice, array_key_exists('unit_price', $part->getAttributes()));
        $this->assertSame($seesCost, array_key_exists('cost_price', $part->getAttributes()));
    }

    #[DataProvider('tierProvider')]
    public function test_an_omitted_column_throws_rather_than_reading_as_empty(
        string $role,
        bool $seesPrice,
        bool $seesCost,
    ): void {
        $this->part();
        $user = User::factory()->{$role}()->create();

        $part = Part::query()->visibleTo($user)->sole();

        if (! $seesPrice) {
            $this->expectException(MissingAttributeException::class);
            $part->unit_price;

            return;
        }

        $this->assertSame(self::PRICE, $part->unit_price);

        if (! $seesCost) {
            $this->expectException(MissingAttributeException::class);
            $part->cost_price;

            return;
        }

        $this->assertSame(self::COST, $part->cost_price);
    }

    #[DataProvider('tierProvider')]
    public function test_every_tier_keeps_the_columns_the_catalogue_needs(string $role): void
    {
        $this->part();
        $user = User::factory()->{$role}()->create();

        $part = Part::query()->visibleTo($user)->sole();

        foreach (Part::BASE_COLUMNS as $column) {
            $this->assertArrayHasKey($column, $part->getAttributes(), "{$column} must be selected for a {$role}.");
        }
    }

    public function test_the_column_lists_account_for_every_column_in_the_table(): void
    {
        $this->assertEqualsCanonicalizing(
            Schema::getColumnListing('parts'),
            [...Part::BASE_COLUMNS, ...Part::PRICE_COLUMNS],
            'A column exists that no visibility tier declares, so it would silently fall out of every query.'
        );
    }

    public function test_a_receptionist_editing_a_part_cannot_erase_the_cost_price_they_never_see(): void
    {
        $part = $this->part();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('parts.update', $part), [
                'sku' => 'BP-2210',
                'name' => 'Brake pads front, revised',
                'unit_price' => '4500.00',
                'reorder_level' => 6,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $part->fresh();

        $this->assertSame('Brake pads front, revised', $fresh->name);
        $this->assertSame('4500.00', $fresh->unit_price);
        $this->assertSame(self::COST, $fresh->cost_price);
    }

    public function test_a_trashed_part_is_still_readable_through_the_scope(): void
    {
        Part::factory()->deleted()->create(['sku' => 'WB-24']);
        $user = User::factory()->mechanic()->create();

        $part = Part::query()->visibleTo($user)->withTrashed()->sole();

        $this->assertTrue($part->trashed());
    }

    #[DataProvider('tierProvider')]
    public function test_the_catalogue_page_never_renders_a_price_the_viewer_may_not_see(
        string $role,
        bool $seesPrice,
        bool $seesCost,
    ): void {
        $this->part();
        $user = User::factory()->{$role}()->create();

        $body = $this->actingAs($user)->get(route('parts.index'))->assertOk()->getContent();

        $this->assertSame($seesPrice, str_contains($body, '4,200.00'));
        $this->assertSame($seesCost, str_contains($body, '2,850.00'));
    }

    #[DataProvider('tierProvider')]
    public function test_the_detail_page_never_renders_a_price_the_viewer_may_not_see(
        string $role,
        bool $seesPrice,
        bool $seesCost,
    ): void {
        $part = $this->part();
        $user = User::factory()->{$role}()->create();

        $body = $this->actingAs($user)->get(route('parts.show', $part))->assertOk()->getContent();

        $this->assertSame($seesPrice, str_contains($body, '4,200.00'));
        $this->assertSame($seesCost, str_contains($body, '2,850.00'));
    }
}
