<?php

namespace Tests\Feature\Parts;

use App\Enums\PartNumberType;
use App\Models\Part;
use App\Models\PartNumber;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartNumberSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'part_id', 'type', 'number', 'number_normalized',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('part_numbers'));
    }

    public function test_the_same_number_and_type_cannot_repeat_on_one_part(): void
    {
        $part = Part::factory()->create();

        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();

        $this->expectException(QueryException::class);

        PartNumber::factory()->for($part)->oe()->number('1K0 615 301 AA')->create();
    }

    public function test_the_same_number_may_repeat_under_a_different_type(): void
    {
        $part = Part::factory()->create();

        PartNumber::factory()->for($part)->oe()->number('1K0615301AA')->create();
        PartNumber::factory()->for($part)->catalog()->number('1K0615301AA')->create();

        $this->assertSame(2, $part->numbers()->count());
    }

    /**
     * The whole point of an OE number: two brands' parts answer to the same one.
     */
    public function test_the_same_oe_number_may_sit_on_two_different_parts(): void
    {
        $first = Part::factory()->create();
        $second = Part::factory()->create();

        PartNumber::factory()->for($first)->oe()->number('1K0615301AA')->create();
        PartNumber::factory()->for($second)->oe()->number('1K0615301AA')->create();

        $this->assertSame(2, PartNumber::query()->normalizedAs('1K0615301AA')->count());
    }

    public function test_deleting_a_part_outright_takes_its_numbers_with_it(): void
    {
        $part = Part::factory()->create();
        PartNumber::factory()->for($part)->count(3)->create();

        $part->forceDelete();

        $this->assertSame(0, PartNumber::query()->where('part_id', $part->getKey())->count());
    }

    public function test_soft_deleting_a_part_keeps_its_numbers(): void
    {
        $part = Part::factory()->create();
        PartNumber::factory()->for($part)->count(3)->create();

        $part->delete();

        $this->assertSame(3, PartNumber::query()->where('part_id', $part->getKey())->count());
    }

    public function test_the_type_is_cast_to_the_enum(): void
    {
        $number = PartNumber::factory()->ean()->create();

        $this->assertSame(PartNumberType::Ean, $number->fresh()->type);
    }
}
