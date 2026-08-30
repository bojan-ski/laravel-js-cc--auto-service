<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PartValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'OF-1042',
            'name' => 'Oil filter',
            'description' => 'Fits Golf IV',
            'unit_price' => '850.00',
            'reorder_level' => 4,
            'location' => 'A3',
        ], $overrides);
    }

    public function test_the_required_fields_are_enforced(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), ['sku' => '', 'name' => '', 'unit_price' => '', 'reorder_level' => ''])
            ->assertSessionHasErrors(['sku', 'name', 'unit_price', 'reorder_level']);
    }

    public function test_the_length_and_range_limits_are_enforced(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload([
                'sku' => str_repeat('a', 41),
                'name' => str_repeat('b', 121),
                'description' => str_repeat('c', 2001),
                'unit_price' => '100000000.00',
                'reorder_level' => -1,
                'location' => str_repeat('d', 61),
            ]))
            ->assertSessionHasErrors(['sku', 'name', 'description', 'unit_price', 'reorder_level', 'location']);
    }

    public function test_a_negative_unit_price_is_rejected(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload(['unit_price' => '-1']))
            ->assertSessionHasErrors('unit_price');
    }

    public function test_a_receptionist_may_not_submit_a_cost_price(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload(['cost_price' => '600.00']))
            ->assertSessionHasErrors('cost_price');

        $this->assertSame(0, Part::query()->count());
    }

    public function test_an_empty_cost_price_from_a_receptionist_passes_and_is_never_stored(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload(['cost_price' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Part::query()->sole()->cost_price);
    }

    public function test_an_admin_may_set_and_then_clear_the_cost_price(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('parts.store'), $this->payload(['cost_price' => '600.00']))
            ->assertSessionHasNoErrors();

        $part = Part::query()->sole();
        $this->assertSame('600.00', $part->cost_price);

        $this->actingAs($admin)
            ->put(route('parts.update', $part), $this->payload(['cost_price' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull($part->fresh()->cost_price);
    }

    public function test_stock_can_never_be_submitted_through_the_form(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('parts.store'), $this->payload(['quantity_in_stock' => 99]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Part::query()->sole()->quantity_in_stock);
    }

    public function test_a_sku_that_belongs_to_an_active_part_is_rejected(): void
    {
        Part::factory()->create(['sku' => 'OF-1042']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload())
            ->assertSessionHasErrors('sku');

        $this->assertSame(
            __('validation.unique', ['attribute' => 'sku']),
            session('errors')->first('sku'),
        );
    }

    public function test_a_sku_that_belongs_to_a_deleted_part_is_rejected_with_a_distinct_message(): void
    {
        Part::factory()->deleted()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload())
            ->assertSessionHasErrors('sku');

        $message = session('errors')->first('sku');

        $this->assertSame(__('parts.validation.sku_deleted', ['name' => 'Oil filter']), $message);
        $this->assertNotSame(__('validation.unique', ['attribute' => 'sku']), $message);
    }

    public function test_a_part_keeps_its_own_sku_on_update(): void
    {
        $part = Part::factory()->create(['sku' => 'OF-1042']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('parts.update', $part), $this->payload(['name' => 'Oil filter renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Oil filter renamed', $part->fresh()->name);
    }

    public function test_a_restored_parts_sku_conflicts_as_an_active_part_again(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'OF-1042', 'name' => 'Oil filter']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('parts.restore', $part));

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload())
            ->assertSessionHasErrors('sku');

        $this->assertSame(
            __('validation.unique', ['attribute' => 'sku']),
            session('errors')->first('sku'),
        );
    }

    public function test_a_sku_is_usable_again_once_the_deleted_part_holding_it_is_renamed_away(): void
    {
        $deleted = Part::factory()->deleted()->create(['sku' => 'OF-1042']);
        $deleted->forceDelete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('OF-1042', Part::query()->sole()->sku);
    }
}
