<?php

namespace Tests\Feature\Admin;

use App\Enums\QualityTier;
use App\Models\Brand;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'quality_tier', 'deleted_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('brands'),
        );
    }

    public function test_an_admin_creates_a_brand(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.brands.store'), [
                'name' => 'Bosch',
                'quality_tier' => QualityTier::Original->value,
            ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHas('success');

        $brand = Brand::query()->sole();

        $this->assertSame('Bosch', $brand->name);
        $this->assertSame(QualityTier::Original, $brand->quality_tier);
    }

    public function test_an_admin_edits_a_brand(): void
    {
        $brand = Brand::factory()->aftermarket()->create(['name' => 'Bosh']);

        $this->actingAs($this->admin())
            ->put(route('admin.brands.update', $brand), [
                'name' => 'Bosch',
                'quality_tier' => QualityTier::Genuine->value,
            ])
            ->assertRedirect(route('admin.brands.index'));

        $this->assertSame('Bosch', $brand->fresh()->name);
        $this->assertSame(QualityTier::Genuine, $brand->fresh()->quality_tier);
    }

    public function test_a_duplicate_name_is_rejected(): void
    {
        Brand::factory()->create(['name' => 'Bosch']);

        $this->actingAs($this->admin())
            ->post(route('admin.brands.store'), [
                'name' => 'Bosch',
                'quality_tier' => QualityTier::Original->value,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Brand::query()->count());
    }

    public function test_a_name_belonging_to_a_trashed_brand_is_refused_with_its_own_message(): void
    {
        Brand::factory()->trashed()->create(['name' => 'Bosch']);

        $this->actingAs($this->admin())
            ->post(route('admin.brands.store'), [
                'name' => 'Bosch',
                'quality_tier' => QualityTier::Original->value,
            ])
            ->assertSessionHasErrors(['name' => __('brands.validation.name_deleted')]);
    }

    public function test_editing_a_brand_may_keep_its_own_name(): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);

        $this->actingAs($this->admin())
            ->put(route('admin.brands.update', $brand), [
                'name' => 'Bosch',
                'quality_tier' => QualityTier::FirstFit->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(QualityTier::FirstFit, $brand->fresh()->quality_tier);
    }

    public function test_an_unknown_tier_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.brands.store'), ['name' => 'Bosch', 'quality_tier' => 'platinum'])
            ->assertSessionHasErrors('quality_tier');
    }

    public function test_a_missing_name_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.brands.store'), ['quality_tier' => QualityTier::Original->value])
            ->assertSessionHasErrors('name');
    }

    public function test_deleting_a_brand_soft_deletes_it(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.brands.destroy', $brand))
            ->assertRedirect(route('admin.brands.index'));

        $this->assertSoftDeleted($brand);
    }

    public function test_a_deleted_brand_is_restored(): void
    {
        $brand = Brand::factory()->trashed()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.brands.restore', $brand))
            ->assertRedirect(route('admin.brands.index'));

        $this->assertNotSoftDeleted($brand);
    }

    public function test_a_live_brand_cannot_be_restored(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.brands.restore', $brand))
            ->assertForbidden();
    }

    /**
     * Only `restore` declares withTrashed(), so a trashed brand is unreachable
     * on every other route and 404s before the policy is consulted — the same
     * shape parts use. `BrandPolicy::delete`'s trashed check therefore cannot
     * be reached over HTTP; it stays as defence in depth for direct calls,
     * which the next test exercises.
     */
    public function test_a_trashed_brand_is_not_found_on_the_delete_route(): void
    {
        $brand = Brand::factory()->trashed()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.brands.destroy', $brand))
            ->assertNotFound();

        $this->assertSoftDeleted($brand);
    }

    public function test_the_policy_refuses_deleting_an_already_trashed_brand(): void
    {
        $brand = Brand::factory()->trashed()->create();

        $this->assertFalse($this->admin()->can('delete', $brand));
    }

    public function test_a_trashed_brand_is_not_found_on_the_edit_route(): void
    {
        $brand = Brand::factory()->trashed()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.brands.edit', $brand))
            ->assertNotFound();
    }

    public function test_the_index_lists_trashed_brands_with_their_badge(): void
    {
        Brand::factory()->trashed()->create(['name' => 'Retired brand']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Retired brand', $html);
        $this->assertStringContainsString(__('brands.trashed.badge'), $html);
    }

    public function test_the_index_shows_how_many_parts_carry_each_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);
        Part::factory()->forBrand($brand)->count(3)->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(trans_choice('brands.parts_count', 3, ['count' => 3]), $html);
    }

    public function test_the_part_count_includes_trashed_parts(): void
    {
        $brand = Brand::factory()->create();
        Part::factory()->forBrand($brand)->create();
        Part::factory()->forBrand($brand)->deleted()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(trans_choice('brands.parts_count', 2, ['count' => 2]), $html);
    }

    public function test_the_index_searches_by_name(): void
    {
        Brand::factory()->create(['name' => 'Bosch']);
        Brand::factory()->create(['name' => 'Febi Bilstein']);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.brands.index', ['search' => 'Febi']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Febi Bilstein', $html);
        $this->assertStringNotContainsString('Bosch', $html);
    }

    public function test_the_empty_state_distinguishes_no_brands_from_no_matches(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee(__('brands.empty.none.title'));

        Brand::factory()->create(['name' => 'Bosch']);

        $this->actingAs($admin)
            ->get(route('admin.brands.index', ['search' => 'nothing matches this']))
            ->assertOk()
            ->assertSee(__('brands.empty.no_matches.title'));
    }

    public function test_the_edit_form_is_prefilled(): void
    {
        $brand = Brand::factory()->genuine()->create(['name' => 'Bosch']);

        $this->actingAs($this->admin())
            ->get(route('admin.brands.edit', $brand))
            ->assertOk()
            ->assertSee('value="Bosch"', false)
            ->assertSee(__('brands.tiers.genuine'));
    }

    public function test_the_update_route_spoofs_put(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.brands.edit', $brand))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);
    }
}
