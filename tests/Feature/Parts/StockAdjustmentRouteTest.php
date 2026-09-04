<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class StockAdjustmentRouteTest extends TestCase
{
    use AssertsStockInvariant, LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return [
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    public function test_a_guest_is_redirected_from_both_routes(): void
    {
        $part = Part::factory()->create();

        $this->get(route('parts.adjust.create', $part))->assertRedirect(route('login'));
        $this->post(route('parts.adjust.store', $part))->assertRedirect(route('login'));
    }

    public function test_a_mechanic_is_refused_the_form(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('parts.adjust.create', $part))
            ->assertForbidden();
    }

    public function test_a_mechanic_is_refused_the_write_and_moves_no_stock(): void
    {
        $part = Part::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::Restock->value,
                'quantity_change' => 5,
            ])
            ->assertForbidden();

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_open_the_form(string $role): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.adjust.create', $part))
            ->assertOk()
            ->assertSee(__('parts.adjust.title', ['sku' => 'BP-2210']))
            ->assertSee(__('parts.adjust.help.restock'))
            ->assertSee(__('parts.adjust.help.manual_adjustment'));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_record_a_restock(string $role): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::Restock->value,
                'quantity_change' => 12,
                'note' => 'delivery 4471',
            ])
            ->assertRedirect(route('parts.show', $part));

        $this->assertSame(12, $part->fresh()->quantity_in_stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $part->getKey(),
            'user_id' => $user->getKey(),
            'quantity_change' => 12,
            'reason' => StockMovementReason::Restock->value,
            'note' => 'delivery 4471',
        ]);
        $this->assertStockMatchesLedger();
    }

    public function test_the_success_flash_names_the_change_and_the_new_balance(): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::Restock->value,
                'quantity_change' => 12,
            ])
            ->assertSessionHas('success', __('parts.flash.stock_recorded', [
                'sku' => 'BP-2210',
                'change' => '+12',
                'balance' => 12,
            ]))
            ->assertSessionMissing('warning');
    }

    /**
     * The boundary itself: a movement landing on exactly zero is not negative,
     * so it must not warn. Without this, widening the controller's check to
     * `<= 0` would leave the whole suite green.
     */
    public function test_a_movement_landing_on_exactly_zero_does_not_warn(): void
    {
        $part = Part::factory()->create();
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)->post(route('parts.adjust.store', $part), [
            'reason' => StockMovementReason::Restock->value,
            'quantity_change' => 5,
        ])->assertSessionMissing('warning');

        $this->actingAs($user)->post(route('parts.adjust.store', $part), [
            'reason' => StockMovementReason::ManualAdjustment->value,
            'quantity_change' => -5,
            'note' => 'count correction',
        ])->assertSessionMissing('warning');

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_movement_ending_below_zero_succeeds_and_warns(): void
    {
        $part = Part::factory()->create(['sku' => 'BP-2210']);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::ManualAdjustment->value,
                'quantity_change' => -3,
                'note' => 'count correction',
            ]);

        $response->assertSessionHas('warning', __('parts.flash.stock_negative', [
            'sku' => 'BP-2210',
            'balance' => -3,
        ]));

        $this->assertSame(-3, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_redirect_target_renders_both_flash_regions(): void
    {
        $part = Part::factory()->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::ManualAdjustment->value,
                'quantity_change' => -3,
                'note' => 'count correction',
            ])
            ->assertRedirect(route('parts.show', $part));

        $html = $this->followRedirects($response)->assertOk()->getContent();

        $this->assertStringContainsString('data-flash="success"', $html);
        $this->assertStringContainsString('data-flash="warning"', $html);
    }

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
    public function test_a_trashed_part_cannot_be_adjusted_by_anyone(string $role): void
    {
        $part = Part::factory()->deleted()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.adjust.create', $part))
            ->assertNotFound();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('parts.adjust.store', $part), [
                'reason' => StockMovementReason::Restock->value,
                'quantity_change' => 5,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('stock_movements', 0);
    }

    /**
     * The route no longer binds the model, so there is exactly one read of the
     * parts table and it carries the role's scope. Previously binding ran an
     * unscoped `select *` first, hydrating cost_price for every role before the
     * scoped read replaced it. Asserted at SQL level: a rendered page cannot tell
     * a column that is absent from one that is merely not printed.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function pricingProvider(): array
    {
        return [
            'receptionist' => ['receptionist', false],
            'admin' => ['admin', true],
        ];
    }

    #[DataProvider('pricingProvider')]
    public function test_the_form_never_loads_a_cost_price_the_user_may_not_see(
        string $role,
        bool $seesCost,
    ): void {
        $part = Part::factory()->create(['cost_price' => 987.65]);
        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('parts.adjust.create', $part));
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $response->assertOk()->assertDontSee('987.65');

        $resolving = array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`'),
        ));

        $this->assertCount(
            1,
            $resolving,
            "The parts table must be read exactly once, through the scope:\n".implode("\n", $queries),
        );

        $scoped = end($resolving);

        if ($seesCost) {
            $this->assertStringContainsString('select *', $scoped, 'An admin should see every column.');

            return;
        }

        $this->assertStringNotContainsString('select *', $scoped, 'The scope was bypassed entirely.');
        $this->assertStringNotContainsString('cost_price', $scoped, 'A receptionist must never load the cost price.');
        $this->assertStringContainsString('unit_price', $scoped, 'A receptionist should still load the selling price.');
    }

    public function test_the_adjust_control_is_absent_on_a_trashed_part(): void
    {
        $part = Part::factory()->deleted()->create();
        StockMovement::factory()->forPart($part)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertDontSee(route('parts.adjust.create', $part));
    }
}
