<?php

namespace Tests\Feature\Parts;

use App\Enums\Locale;
use App\Enums\StockMovementReason;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockAdjustmentValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submit(array $payload, ?Part $part = null): TestResponse
    {
        $part ??= Part::factory()->create();

        return $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.adjust.create', $part))
            ->post(route('parts.adjust.store', $part), $payload);
    }

    /**
     * The two job reasons exist in the enum but no form may produce them, so
     * the ledger can be trusted to say where a movement genuinely came from.
     *
     * @return array<string, array{0: string}>
     */
    public static function rejectedReasonProvider(): array
    {
        return [
            'job use' => [StockMovementReason::JobUse->value],
            'job return' => [StockMovementReason::JobReturn->value],
            'nonsense' => ['stocktake'],
            'empty' => [''],
        ];
    }

    #[DataProvider('rejectedReasonProvider')]
    public function test_the_reason_is_rejected(string $reason): void
    {
        $this->submit(['reason' => $reason, 'quantity_change' => 5])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function rejectedRestockQuantityProvider(): array
    {
        return [
            'zero' => [0],
            'negative zero' => ['-0'],
            'positive zero' => ['+0'],
            'negative' => [-4],
            'fractional' => ['1.5'],
            'blank' => [''],
            'out of range' => [1000000],
        ];
    }

    #[DataProvider('rejectedRestockQuantityProvider')]
    public function test_a_restock_quantity_is_rejected(mixed $quantity): void
    {
        $this->submit([
            'reason' => StockMovementReason::Restock->value,
            'quantity_change' => $quantity,
        ])->assertSessionHasErrors('quantity_change');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function rejectedAdjustmentQuantityProvider(): array
    {
        return [
            'zero' => [0],
            'negative zero' => ['-0'],
            'positive zero' => ['+0'],
            'blank' => [''],
            'fractional' => ['-1.5'],
        ];
    }

    #[DataProvider('rejectedAdjustmentQuantityProvider')]
    public function test_an_adjustment_quantity_is_rejected(mixed $quantity): void
    {
        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => $quantity,
            'note' => 'count correction',
        ])->assertSessionHasErrors('quantity_change');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_adjustment_may_be_negative(): void
    {
        $part = Part::factory()->create();

        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
            'note' => 'count correction',
        ], $part)->assertSessionHasNoErrors();

        $this->assertSame(-3, $part->fresh()->quantity_in_stock);
    }

    public function test_an_adjustment_without_a_note_is_rejected(): void
    {
        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
        ])->assertSessionHasErrors('note');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_adjustment_with_a_blank_note_is_rejected(): void
    {
        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
            'note' => '',
        ])->assertSessionHasErrors('note');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_restock_without_a_note_is_accepted_and_stores_null(): void
    {
        $part = Part::factory()->create();

        $this->submit([
            'reason' => StockMovementReason::Restock->value,
            'quantity_change' => 6,
        ], $part)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $part->getKey(),
            'quantity_change' => 6,
            'note' => null,
        ]);
    }

    public function test_a_blank_restock_note_is_stored_as_null_rather_than_an_empty_string(): void
    {
        $part = Part::factory()->create();

        $this->submit([
            'reason' => StockMovementReason::Restock->value,
            'quantity_change' => 6,
            'note' => '',
        ], $part)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $part->getKey(),
            'note' => null,
        ]);
    }

    public function test_a_note_over_the_column_length_is_rejected(): void
    {
        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
            'note' => str_repeat('a', 256),
        ])->assertSessionHasErrors('note');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_note_at_the_column_length_is_accepted(): void
    {
        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
            'note' => str_repeat('a', 255),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stock_movements', 1);
    }

    /**
     * @return array<string, array{Locale}>
     */
    public static function locales(): array
    {
        $locales = [];

        foreach (Locale::cases() as $locale) {
            $locales[$locale->value] = [$locale];
        }

        return $locales;
    }

    #[DataProvider('locales')]
    public function test_a_missing_adjustment_note_is_reported_in_words_not_by_the_reason_value(Locale $locale): void
    {
        $part = Part::factory()->create();
        $expected = trans('parts.validation.adjustment_note_required', [], $locale->value);

        $this->actingAs(User::factory()->receptionist()->create(['locale' => $locale]))
            ->from(route('parts.adjust.create', $part))
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::ManualAdjustment->value,
                'quantity_change' => -3,
            ])
            ->assertRedirect(route('parts.adjust.create', $part));

        $this->assertSame([$expected], app('session.store')->get('errors')['default']['messages']['note'] ?? []);
        $this->assertStringNotContainsString(StockMovementReason::ManualAdjustment->value, $expected);

        $this->get(route('parts.adjust.create', $part))
            ->assertOk()
            ->assertSee($expected);
    }

    public function test_a_rejected_submission_returns_to_the_form_with_its_input(): void
    {
        $part = Part::factory()->create();

        $this->submit([
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -3,
        ], $part)
            ->assertRedirect(route('parts.adjust.create', $part))
            ->assertSessionHasInput('quantity_change', -3);
    }
}
