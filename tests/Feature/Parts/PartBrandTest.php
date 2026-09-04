<?php

namespace Tests\Feature\Parts;

use App\Enums\QualityTier;
use App\Models\Brand;
use App\Models\Part;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartBrandTest extends TestCase
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

    public function test_a_part_may_have_no_brand(): void
    {
        $part = Part::factory()->create();

        $this->assertNull($part->brand_id);
        $this->assertNull($part->brand);
    }

    public function test_a_part_reads_its_brand(): void
    {
        $brand = Brand::factory()->original()->create(['name' => 'Bosch']);
        $part = Part::factory()->forBrand($brand)->create();

        $this->assertTrue($part->fresh()->brand->is($brand));
        $this->assertSame(QualityTier::Original, $part->fresh()->brand->quality_tier);
    }

    public function test_a_brand_with_parts_cannot_be_hard_deleted(): void
    {
        $brand = Brand::factory()->create();
        Part::factory()->forBrand($brand)->create();

        $this->expectException(QueryException::class);

        $brand->forceDelete();
    }

    public function test_a_soft_deleted_brand_still_resolves_from_its_parts(): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);
        $part = Part::factory()->forBrand($brand)->create();

        $brand->delete();

        $this->assertSame('Bosch', $part->fresh()->brand->name);
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_sees_the_brand_on_the_detail_page(string $role): void
    {
        $brand = Brand::factory()->genuine()->create(['name' => 'Bosch']);
        $part = Part::factory()->forBrand($brand)->create();

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Bosch', $html);
        $this->assertStringContainsString(__('brands.tiers.genuine'), $html);
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_sees_the_brand_column_on_the_catalogue(string $role): void
    {
        $brand = Brand::factory()->create(['name' => 'Bosch']);
        Part::factory()->forBrand($brand)->create();

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Bosch', $html);
        $this->assertStringContainsString(__('parts.headers.brand'), $html);
    }

    public function test_front_office_may_set_a_brand_and_category_on_create(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), [
                'sku' => 'BP-9001',
                'name' => 'Brake pads front',
                'brand_id' => (string) $brand->getKey(),
                'category' => 'Braking',
                'unit_price' => '4200.00',
                'reorder_level' => '4',
            ])
            ->assertSessionHasNoErrors();

        $part = Part::query()->where('sku', 'BP-9001')->sole();

        $this->assertSame($brand->getKey(), $part->brand_id);
        $this->assertSame('Braking', $part->category);
    }

    public function test_an_empty_brand_and_category_are_stored_as_null(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), [
                'sku' => 'BP-9002',
                'name' => 'Brake pads rear',
                'brand_id' => '',
                'category' => '',
                'unit_price' => '4200.00',
                'reorder_level' => '4',
            ])
            ->assertSessionHasNoErrors();

        $part = Part::query()->where('sku', 'BP-9002')->sole();

        $this->assertNull($part->brand_id);
        $this->assertNull($part->category);
    }

    public function test_an_unknown_brand_is_rejected(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), [
                'sku' => 'BP-9003',
                'name' => 'Brake pads',
                'brand_id' => '999999',
                'unit_price' => '4200.00',
                'reorder_level' => '4',
            ])
            ->assertSessionHasErrors('brand_id');
    }

    public function test_a_soft_deleted_brand_cannot_be_selected(): void
    {
        $brand = Brand::factory()->trashed()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), [
                'sku' => 'BP-9004',
                'name' => 'Brake pads',
                'brand_id' => (string) $brand->getKey(),
                'unit_price' => '4200.00',
                'reorder_level' => '4',
            ])
            ->assertSessionHasErrors('brand_id');
    }

    public function test_the_category_datalist_offers_values_already_in_use(): void
    {
        Part::factory()->inCategory('Braking')->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<datalist id="part-categories">', $html);
        $this->assertStringContainsString('<option value="Braking">', $html);
    }

    public function test_a_category_longer_than_the_column_is_rejected(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.store'), [
                'sku' => 'BP-9005',
                'name' => 'Brake pads',
                'category' => str_repeat('a', 61),
                'unit_price' => '4200.00',
                'reorder_level' => '4',
            ])
            ->assertSessionHasErrors('category');
    }
}
