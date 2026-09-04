<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every /parts/{part} route used to bind the model implicitly, so an unscoped
 * `select *` hydrated unit_price and cost_price for every role before the
 * controller's scoped read replaced it. Nothing rendered them, but it left the
 * money boundary resting on the view rather than on the query — the same defect
 * the job-item routes carried until fix/scoped-job-item-binding.
 */
class PartBindingScopeTest extends TestCase
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
    public function test_the_part_page_reads_the_table_once_within_the_roles_scope(string $role): void
    {
        $part = Part::factory()->create(['cost_price' => 987.65]);

        $reads = $this->partReads(
            fn () => $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('parts.show', $part))
                ->assertOk()
        );

        $this->assertCount(1, $reads, implode("\n", $reads));

        if ($role === 'admin') {
            $this->assertStringStartsWith('select * from `parts`', $reads[0]);

            return;
        }

        // `select *` names no columns, so a "does not contain cost_price"
        // assertion would pass vacuously against an unscoped read.
        $this->assertStringNotContainsString('select *', $reads[0]);
        $this->assertStringContainsString('`sku`', $reads[0]);
        $this->assertStringNotContainsString('cost_price', $reads[0]);

        $role === 'mechanic'
            ? $this->assertStringNotContainsString('unit_price', $reads[0])
            : $this->assertStringContainsString('unit_price', $reads[0]);
    }

    public function test_the_edit_form_reads_the_table_once_without_the_cost_price(): void
    {
        $part = Part::factory()->create(['cost_price' => 987.65]);

        $reads = $this->partReads(
            fn () => $this->actingAs(User::factory()->receptionist()->create())
                ->get(route('parts.edit', $part))
                ->assertOk()
        );

        $this->assertCount(1, $reads, implode("\n", $reads));
        $this->assertStringNotContainsString('select *', $reads[0]);
        $this->assertStringNotContainsString('cost_price', $reads[0]);
    }

    public function test_an_update_never_reads_the_cost_price_for_a_receptionist(): void
    {
        $part = Part::factory()->create(['cost_price' => 987.65, 'sku' => 'BP-2210']);

        $reads = $this->partReads(
            fn () => $this->actingAs(User::factory()->receptionist()->create())
                ->put(route('parts.update', $part), [
                    'sku' => 'BP-2210',
                    'name' => 'Renamed by the counter',
                    'unit_price' => '4200.00',
                    'reorder_level' => '4',
                ])
                ->assertSessionHasNoErrors()
        );

        $this->assertNotEmpty($reads);

        foreach ($reads as $read) {
            $this->assertStringNotContainsString('cost_price', $read, $read);
        }

        $this->assertSame('Renamed by the counter', $part->fresh()->name);
        $this->assertSame('987.65', $part->fresh()->cost_price);
    }

    public function test_the_unique_sku_rule_still_ignores_the_record_being_edited(): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('parts.update', $part), [
                'sku' => 'BP-2210',
                'name' => 'Same SKU, new name',
                'unit_price' => '1000.00',
                'reorder_level' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Same SKU, new name', $part->fresh()->name);
    }

    public function test_a_missing_part_still_produces_a_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', 999999))
            ->assertNotFound();
    }

    /**
     * @param  callable(): mixed  $act
     * @return list<string>
     */
    private function partReads(callable $act): array
    {
        DB::enableQueryLog();
        $act();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        return array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`')
                && ! str_starts_with($query, 'select count'),
        ));
    }
}
