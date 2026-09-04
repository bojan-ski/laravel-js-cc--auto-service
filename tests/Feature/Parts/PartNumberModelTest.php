<?php

namespace Tests\Feature\Parts;

use App\Enums\PartNumberType;
use App\Models\Part;
use App\Models\PartNumber;
use App\Services\IdentifierNormalizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartNumberModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizationProvider(): array
    {
        return [
            'spaces' => ['1K0 615 301 AA', '1K0615301AA'],
            'hyphens' => ['1K0-615-301-AA', '1K0615301AA'],
            'lower case' => ['1k0615301aa', '1K0615301AA'],
            'mixed punctuation' => [' 1k0/615.301_aa ', '1K0615301AA'],
            'dots' => ['A.000.989.08.03', 'A0009890803'],
        ];
    }

    #[DataProvider('normalizationProvider')]
    public function test_the_normalized_column_is_written_on_save(string $typed, string $expected): void
    {
        $number = PartNumber::factory()->number($typed)->create();

        $this->assertSame($expected, $number->fresh()->number_normalized);
    }

    public function test_the_number_is_stored_exactly_as_typed(): void
    {
        $number = PartNumber::factory()->number('1K0 615 301 AA')->create();

        $this->assertSame('1K0 615 301 AA', $number->fresh()->number);
    }

    public function test_the_normalized_column_is_rewritten_when_the_number_changes(): void
    {
        $number = PartNumber::factory()->number('1K0615301AA')->create();

        $number->number = '8K0-698-451-D';
        $number->save();

        $this->assertSame('8K0698451D', $number->fresh()->number_normalized);
    }

    /**
     * The guard, not the caller, owns the column: a request that tries to set
     * it directly is overwritten rather than trusted.
     */
    public function test_a_supplied_normalized_value_is_overwritten_by_the_guard(): void
    {
        $number = new PartNumber;
        $number->part_id = Part::factory()->create()->getKey();
        $number->fill(['type' => PartNumberType::Oe, 'number' => '1K0 615 301 AA']);
        $number->number_normalized = 'NONSENSE';
        $number->save();

        $this->assertSame('1K0615301AA', $number->fresh()->number_normalized);
    }

    public function test_the_normalized_column_is_not_mass_assignable(): void
    {
        $number = new PartNumber;
        $number->part_id = Part::factory()->create()->getKey();

        $number->fill([
            'type' => PartNumberType::Oe,
            'number' => 'ABC123',
            'number_normalized' => 'INJECTED',
        ]);

        $this->assertNull($number->getAttributes()['number_normalized'] ?? null);
    }

    public function test_a_part_lists_its_numbers(): void
    {
        $part = Part::factory()->create();
        PartNumber::factory()->for($part)->count(2)->create();

        $this->assertCount(2, $part->numbers()->get());
    }

    public function test_the_relation_reaches_a_trashed_part(): void
    {
        $part = Part::factory()->create();
        $number = PartNumber::factory()->for($part)->create();

        $part->delete();

        $this->assertTrue($number->fresh()->part->is($part));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unusableProvider(): array
    {
        return [
            'punctuation only' => ['---'],
            'spaces only' => ['   '],
            'empty' => [''],
        ];
    }

    #[DataProvider('unusableProvider')]
    public function test_a_value_with_no_alphanumerics_normalizes_to_nothing(string $value): void
    {
        $this->assertSame('', IdentifierNormalizer::normalize($value));
    }

    public function test_the_normalizer_handles_null(): void
    {
        $this->assertSame('', IdentifierNormalizer::normalize(null));
    }
}
