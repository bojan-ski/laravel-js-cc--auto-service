<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Exceptions\InvalidStockMovement;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class StockMovementModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updating_a_persisted_movement_throws(): void
    {
        $movement = StockMovement::factory()->create();

        try {
            $movement->note = 'a correction someone tried to sneak in';
            $movement->save();
            $this->fail('Updating a movement should be refused.');
        } catch (InvalidStockMovement $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertNull($movement->fresh()->note);
    }

    public function test_deleting_a_persisted_movement_throws(): void
    {
        $movement = StockMovement::factory()->create();

        try {
            $movement->delete();
            $this->fail('Deleting a movement should be refused.');
        } catch (InvalidStockMovement $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->getKey()]);
    }

    public function test_the_refusal_carries_the_part_the_change_and_the_reason(): void
    {
        $part = Part::factory()->create();
        $movement = StockMovement::factory()->forPart($part)->jobUse(3)->create();

        try {
            $movement->delete();
            $this->fail('Deleting a movement should be refused.');
        } catch (InvalidStockMovement $exception) {
            $this->assertSame($part->getKey(), $exception->part->getKey());
            $this->assertSame(-3, $exception->quantityChange);
            $this->assertSame(StockMovementReason::JobUse, $exception->reason);
        }
    }

    public function test_a_movement_on_a_soft_deleted_part_can_still_be_refused(): void
    {
        $part = Part::factory()->create();
        $movement = StockMovement::factory()->forPart($part)->create();
        $part->delete();

        $this->expectException(InvalidStockMovement::class);

        $movement->delete();
    }

    /**
     * Eloquent already defaults to `$fillable = []` and `$guarded = ['*']`, so
     * the behavioural assertions below would pass with the attribute deleted.
     * The spec requires the declaration itself, so read it back off the class.
     */
    public function test_the_model_declares_an_empty_fillable_attribute(): void
    {
        $attributes = (new ReflectionClass(StockMovement::class))->getAttributes(Fillable::class);

        $this->assertCount(1, $attributes, 'StockMovement should declare #[Fillable([])].');
        $this->assertSame([], $attributes[0]->newInstance()->columns);
    }

    public function test_nothing_is_mass_assignable(): void
    {
        $movement = new StockMovement;

        $this->assertSame([], $movement->getFillable());
        $this->assertTrue($movement->totallyGuarded());

        $this->expectException(MassAssignmentException::class);

        $movement->fill([
            'part_id' => 99,
            'job_item_id' => 99,
            'user_id' => 99,
            'quantity_change' => 99,
            'reason' => StockMovementReason::Restock->value,
            'note' => 'mass assigned',
        ]);
    }

    public function test_the_reason_is_cast_to_its_enum(): void
    {
        $movement = StockMovement::factory()->manualAdjustment()->create();

        $this->assertSame(StockMovementReason::ManualAdjustment, $movement->fresh()->reason);
    }

    public function test_the_part_relation_resolves_a_trashed_part(): void
    {
        $part = Part::factory()->create();
        $movement = StockMovement::factory()->forPart($part)->create();
        $part->delete();

        $resolved = StockMovement::with('part')->find($movement->getKey())->part;

        $this->assertNotNull($resolved, 'A movement must still name its part after the part is deleted.');
        $this->assertTrue($resolved->trashed());
    }

    public function test_the_causing_user_resolves_after_being_soft_deleted(): void
    {
        $user = User::factory()->receptionist()->create();
        $movement = StockMovement::factory()->causedBy($user)->create();
        $user->delete();

        $resolved = StockMovement::with('causedBy')->find($movement->getKey())->causedBy;

        $this->assertNotNull($resolved, 'A movement must still name who caused it after they are offboarded.');
        $this->assertTrue($resolved->trashed());
        $this->assertSame($user->name, $resolved->name);
    }

    public function test_the_job_item_relation_is_null_when_no_line_caused_the_movement(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertNull(StockMovement::with('jobItem')->find($movement->getKey())->jobItem);
    }

    public function test_the_job_item_relation_resolves_when_a_line_caused_the_movement(): void
    {
        $item = JobItem::factory()->create();
        $movement = StockMovement::factory()->jobUse()->forJobItem($item)->create();

        $this->assertSame(
            $item->getKey(),
            StockMovement::with('jobItem')->find($movement->getKey())->jobItem->getKey(),
        );
    }

    public function test_a_part_lists_its_movements_newest_first(): void
    {
        $part = Part::factory()->create();

        $oldest = StockMovement::factory()->forPart($part)->restock(1)->create();
        $middle = StockMovement::factory()->forPart($part)->restock(2)->create();
        $newest = StockMovement::factory()->forPart($part)->restock(3)->create();

        $this->assertSame(
            [$newest->getKey(), $middle->getKey(), $oldest->getKey()],
            $part->stockMovements()->pluck('id')->all(),
        );
    }

    public function test_an_increase_and_a_decrease_are_distinguishable(): void
    {
        $this->assertTrue(StockMovement::factory()->restock(5)->create()->isIncrease());
        $this->assertFalse(StockMovement::factory()->jobUse(5)->create()->isIncrease());
    }
}
