<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use App\Services\PartService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PartServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): PartService
    {
        return app(PartService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(): array
    {
        return [
            'sku' => 'OF-1042',
            'name' => 'Oil filter',
            'description' => null,
            'unit_price' => '850.00',
            'cost_price' => '600.00',
            'reorder_level' => 4,
            'location' => 'A3',
        ];
    }

    public function test_create_stores_the_supplied_attributes(): void
    {
        $part = $this->service()->create($this->attributes());

        $fresh = $part->fresh();

        $this->assertSame('OF-1042', $fresh->sku);
        $this->assertSame('Oil filter', $fresh->name);
        $this->assertSame('850.00', $fresh->unit_price);
        $this->assertSame('600.00', $fresh->cost_price);
        $this->assertSame(4, $fresh->reorder_level);
        $this->assertSame('A3', $fresh->location);
    }

    public function test_create_forces_stock_to_zero_even_when_it_is_supplied(): void
    {
        $part = $this->service()->create([...$this->attributes(), 'quantity_in_stock' => 99]);

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
    }

    /**
     * The service assigns the column rather than leaning on the database
     * default, so the returned instance can be read without a refetch. Under
     * preventAccessingMissingAttributes the default alone would throw here.
     */
    public function test_the_returned_part_can_report_its_stock_without_a_refetch(): void
    {
        $part = $this->service()->create($this->attributes());

        $this->assertSame(0, $part->quantity_in_stock);
        $this->assertFalse($part->isOutOfStock() && $part->hasNegativeStock());
    }

    public function test_update_changes_the_supplied_attributes_only(): void
    {
        $part = Part::factory()->inStock()->create(['sku' => 'OF-1042']);
        $stockBefore = $part->quantity_in_stock;

        $this->service()->update($part, ['name' => 'Oil filter renamed', 'location' => 'B7']);

        $fresh = $part->fresh();

        $this->assertSame('Oil filter renamed', $fresh->name);
        $this->assertSame('B7', $fresh->location);
        $this->assertSame('OF-1042', $fresh->sku);
        $this->assertSame($stockBefore, $fresh->quantity_in_stock);
    }

    public function test_update_cannot_move_stock(): void
    {
        $part = Part::factory()->inStock()->create();
        $stockBefore = $part->quantity_in_stock;

        $this->service()->update($part, ['quantity_in_stock' => 999]);

        $this->assertSame($stockBefore, $part->fresh()->quantity_in_stock);
    }

    public function test_delete_soft_deletes(): void
    {
        $part = Part::factory()->create();

        $this->service()->delete($part);

        $this->assertSoftDeleted($part);
    }

    public function test_restore_clears_the_deletion_and_changes_nothing_else(): void
    {
        $part = Part::factory()->negativeStock()->deleted()->create(['location' => 'C2']);

        $restored = $this->service()->restore($part);

        $this->assertFalse($restored->fresh()->trashed());
        $this->assertSame(-2, $restored->fresh()->quantity_in_stock);
        $this->assertSame('C2', $restored->fresh()->location);
    }

    public function test_the_paginator_applies_the_visibility_scope(): void
    {
        Part::factory()->create();

        $mechanic = User::factory()->mechanic()->create();

        $part = $this->service()
            ->paginateFiltered(['search' => '', 'filter' => '', 'trashed' => ''], $mechanic, false)
            ->sole();

        $this->assertArrayNotHasKey('unit_price', $part->getAttributes());
        $this->assertArrayNotHasKey('cost_price', $part->getAttributes());
    }

    public function test_each_write_lands_on_a_page_that_renders_its_own_flash(): void
    {
        $admin = User::factory()->admin()->create();

        $created = $this->actingAs($admin)->post(route('parts.store'), [
            'sku' => 'OF-1042',
            'name' => 'Oil filter',
            'unit_price' => '850.00',
            'reorder_level' => 4,
        ]);
        $part = Part::query()->sole();
        $created->assertRedirect(route('parts.show', $part));
        $this->followRedirects($created)->assertOk()
            ->assertSee(__('parts.flash.created', ['sku' => 'OF-1042']));

        $updated = $this->actingAs($admin)->put(route('parts.update', $part), [
            'sku' => 'OF-1042',
            'name' => 'Oil filter revised',
            'unit_price' => '900.00',
            'reorder_level' => 6,
        ]);
        $updated->assertRedirect(route('parts.show', $part));
        $this->followRedirects($updated)->assertOk()
            ->assertSee(__('parts.flash.updated', ['sku' => 'OF-1042']));

        $deleted = $this->actingAs($admin)->delete(route('parts.destroy', $part));
        $deleted->assertRedirect(route('parts.index'));
        $this->followRedirects($deleted)->assertOk()
            ->assertSee(__('parts.flash.deleted', ['sku' => 'OF-1042']));

        $restored = $this->actingAs($admin)->patch(route('parts.restore', $part));
        $restored->assertRedirect(route('parts.show', $part));
        $this->followRedirects($restored)->assertOk()
            ->assertSee(__('parts.flash.restored', ['sku' => 'OF-1042']));
    }

    public function test_has_any_ignores_trashed_parts_unless_asked(): void
    {
        Part::factory()->deleted()->create();

        $this->assertFalse($this->service()->hasAny());
        $this->assertTrue($this->service()->hasAny(true));
    }
}
