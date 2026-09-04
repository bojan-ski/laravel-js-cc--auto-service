<?php

namespace Tests\Feature\Parts;

use App\Enums\PartNumberType;
use App\Models\Brand;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartNumberRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_add_a_number(string $role): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => '1K0 615 301 AA',
            ])
            ->assertRedirect(route('parts.show', $part));

        $number = $part->numbers()->sole();

        $this->assertSame('1K0 615 301 AA', $number->number);
        $this->assertSame('1K0615301AA', $number->number_normalized);
        $this->assertSame(PartNumberType::Oe, $number->type);
    }

    public function test_a_mechanic_may_not_add_a_number(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => 'ABC123',
            ])
            ->assertForbidden();

        $this->assertSame(0, $part->numbers()->count());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $part = Part::factory()->create();

        $this->post(route('parts.numbers.store', $part), [
            'type' => PartNumberType::Oe->value,
            'number' => 'ABC123',
        ])->assertRedirect(route('login'));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_remove_a_number(string $role): void
    {
        $part = Part::factory()->create();
        $number = PartNumber::factory()->for($part)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('parts.numbers.destroy', [$part, $number]))
            ->assertRedirect(route('parts.show', $part));

        $this->assertSame(0, $part->numbers()->count());
    }

    public function test_a_mechanic_may_not_remove_a_number(): void
    {
        $part = Part::factory()->create();
        $number = PartNumber::factory()->for($part)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->delete(route('parts.numbers.destroy', [$part, $number]))
            ->assertForbidden();

        $this->assertSame(1, $part->numbers()->count());
    }

    /**
     * The number is looked up scoped to its part, so quoting another part's id
     * in the URL is a 404 rather than a cross-part delete.
     */
    public function test_a_number_belonging_to_another_part_is_not_found(): void
    {
        $part = Part::factory()->create();
        $other = Part::factory()->create();
        $number = PartNumber::factory()->for($other)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('parts.numbers.destroy', [$part, $number]))
            ->assertNotFound();

        $this->assertSame(1, $other->numbers()->count());
    }

    public function test_a_trashed_part_is_not_found(): void
    {
        $part = Part::factory()->deleted()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => 'ABC123',
            ])
            ->assertNotFound();
    }

    public function test_a_duplicate_number_is_a_field_error_not_a_database_error(): void
    {
        $part = Part::factory()->create();
        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.show', $part))
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => '1k0-615-301-aa',
            ])
            ->assertRedirect(route('parts.show', $part))
            ->assertSessionHasErrors('number');

        $this->assertSame(1, $part->numbers()->count());
    }

    public function test_a_number_that_normalizes_to_nothing_is_rejected(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => '---',
            ])
            ->assertSessionHasErrors('number');

        $this->assertSame(0, $part->numbers()->count());
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => 'nonsense',
                'number' => 'ABC123',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_a_catalogue_collision_under_the_same_brand_warns_and_still_saves(): void
    {
        $brand = Brand::factory()->create();
        $existing = Part::factory()->forBrand($brand)->create();
        $part = Part::factory()->forBrand($brand)->create();

        PartNumber::factory()->for($existing)->catalog()->number('FEB12345')->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Catalog->value,
                'number' => 'feb 12345',
            ])
            ->assertRedirect(route('parts.show', $part))
            ->assertSessionHas('warning')
            ->assertSessionHas('success');

        $this->assertSame(1, $part->numbers()->count());
    }

    public function test_a_catalogue_collision_under_a_different_brand_does_not_warn(): void
    {
        $existing = Part::factory()->forBrand()->create();
        $part = Part::factory()->forBrand()->create();

        PartNumber::factory()->for($existing)->catalog()->number('FEB12345')->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Catalog->value,
                'number' => 'FEB12345',
            ])
            ->assertSessionMissing('warning');
    }

    public function test_a_matching_oe_number_under_the_same_brand_does_not_warn(): void
    {
        $brand = Brand::factory()->create();
        $existing = Part::factory()->forBrand($brand)->create();
        $part = Part::factory()->forBrand($brand)->create();

        PartNumber::factory()->for($existing)->oe()->number('1K0615301AA')->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Oe->value,
                'number' => '1K0615301AA',
            ])
            ->assertSessionMissing('warning');
    }

    public function test_a_brandless_part_never_warns(): void
    {
        $existing = Part::factory()->create();
        $part = Part::factory()->create();

        PartNumber::factory()->for($existing)->catalog()->number('FEB12345')->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.numbers.store', $part), [
                'type' => PartNumberType::Catalog->value,
                'number' => 'FEB12345',
            ])
            ->assertSessionMissing('warning');
    }
}
