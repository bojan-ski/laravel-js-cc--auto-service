<?php

namespace Tests\Feature\JobItems;

use App\Models\Part;
use App\Models\User;
use App\Services\PartService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_sees_the_sku_name_stock_and_shelf(string $role): void
    {
        $part = Part::factory()->create([
            'sku' => 'BP-2210',
            'name' => 'Brake pads front',
            'quantity_in_stock' => 4,
            'reorder_level' => 1,
            'location' => 'B1',
        ]);

        $options = $this->picker($role);

        $this->assertSame(
            [$part->getKey() => 'BP-2210 · Brake pads front · 4 in stock · shelf B1'],
            $options,
        );
    }

    public function test_a_part_with_no_shelf_omits_the_shelf_fragment(): void
    {
        $part = Part::factory()->create([
            'sku' => 'OF-1001',
            'name' => 'Oil filter',
            'quantity_in_stock' => 9,
            'reorder_level' => 1,
            'location' => null,
        ]);

        $this->assertSame(
            [$part->getKey() => 'OF-1001 · Oil filter · 9 in stock'],
            $this->picker(),
        );
    }

    public function test_a_part_at_or_below_its_reorder_level_is_marked_low(): void
    {
        $part = Part::factory()->create([
            'sku' => 'WB-3003',
            'name' => 'Wiper blade 24"',
            'quantity_in_stock' => 2,
            'reorder_level' => 2,
            'location' => null,
        ]);

        $this->assertSame(
            [$part->getKey() => 'WB-3003 · Wiper blade 24" · 2 in stock · LOW'],
            $this->picker(),
        );
    }

    public function test_a_part_at_zero_or_below_is_marked_more_strongly_than_low(): void
    {
        $zero = Part::factory()->create([
            'sku' => 'AA-0001',
            'name' => 'At zero',
            'quantity_in_stock' => 0,
            'reorder_level' => 5,
            'location' => null,
        ]);

        $negative = Part::factory()->create([
            'sku' => 'AA-0002',
            'name' => 'Below zero',
            'quantity_in_stock' => -3,
            'reorder_level' => 5,
            'location' => null,
        ]);

        $options = $this->picker();

        $this->assertSame('AA-0001 · At zero · 0 in stock · OUT OF STOCK', $options[$zero->getKey()]);
        $this->assertSame('AA-0002 · Below zero · -3 in stock · OUT OF STOCK', $options[$negative->getKey()]);
    }

    public function test_soft_deleted_parts_are_excluded(): void
    {
        $active = Part::factory()->create(['name' => 'Active']);
        Part::factory()->create(['name' => 'Retired'])->delete();

        $this->assertSame([$active->getKey()], array_keys($this->picker()));
    }

    public function test_options_are_ordered_by_name(): void
    {
        Part::factory()->create(['name' => 'Zinc washer']);
        Part::factory()->create(['name' => 'Air filter']);
        Part::factory()->create(['name' => 'Muffler clamp']);

        $names = array_map(
            fn (string $label): string => explode(' · ', $label)[1],
            array_values($this->picker()),
        );

        $this->assertSame(['Air filter', 'Muffler clamp', 'Zinc washer'], $names);
    }

    public function test_a_mechanics_picker_query_selects_no_price_column(): void
    {
        Part::factory()->create();

        DB::enableQueryLog();
        $this->picker('mechanic');
        $selects = $this->partSelects();

        $this->assertCount(1, $selects);
        $this->assertStringContainsString('`sku`', $selects[0]);
        $this->assertStringNotContainsString('select *', $selects[0]);
        $this->assertStringNotContainsString('unit_price', $selects[0]);
        $this->assertStringNotContainsString('cost_price', $selects[0]);
    }

    public function test_a_receptionists_picker_query_selects_no_cost_price(): void
    {
        Part::factory()->create();

        DB::enableQueryLog();
        $this->picker('receptionist');
        $selects = $this->partSelects();

        $this->assertCount(1, $selects);
        $this->assertStringContainsString('`sku`', $selects[0]);
        $this->assertStringNotContainsString('select *', $selects[0]);
        $this->assertStringNotContainsString('cost_price', $selects[0]);
    }

    public function test_an_admins_picker_query_selects_no_price_column(): void
    {
        Part::factory()->create();

        DB::enableQueryLog();
        $this->picker('admin');
        $selects = $this->partSelects();

        $this->assertCount(1, $selects);
        $this->assertStringContainsString('`sku`', $selects[0]);
        $this->assertStringNotContainsString('select *', $selects[0]);
        $this->assertStringNotContainsString('unit_price', $selects[0]);
        $this->assertStringNotContainsString('cost_price', $selects[0]);
    }

    /**
     * @return list<string>
     */
    private function partSelects(): array
    {
        $queries = array_column(DB::getQueryLog(), 'query');

        DB::disableQueryLog();

        return array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`'),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function picker(string $role = 'receptionist'): array
    {
        $this->actingAs(User::factory()->{$role}()->create());

        return app(PartService::class)->pickerOptions();
    }
}
