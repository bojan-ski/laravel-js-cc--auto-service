<?php

namespace Tests\Feature\JobItems;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobPartLineValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'a missing part' => [['part_id' => null], 'part_id'],
            'a part that does not exist' => [['part_id' => 999999], 'part_id'],
            'a missing quantity' => [['quantity' => null], 'quantity'],
            'a fractional quantity' => [['quantity' => '0.5'], 'quantity'],
            'a whole quantity written with decimals' => [['quantity' => '2.00'], 'quantity'],
            'a zero quantity' => [['quantity' => '0'], 'quantity'],
            'a negative quantity' => [['quantity' => '-1'], 'quantity'],
            'a non-numeric quantity' => [['quantity' => 'two'], 'quantity'],
            'a quantity beyond the column' => [['quantity' => '1000000'], 'quantity'],
            'a description over 255 characters' => [['description' => str_repeat('a', 256)], 'description'],
            'a user that does not exist' => [['user_id' => 999999], 'user_id'],
            'a negative price' => [['unit_price' => '-1'], 'unit_price'],
            'a price with three decimals' => [['unit_price' => '10.005'], 'unit_price'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_the_add_part_form_rejects_bad_input(array $overrides, string $field): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), $this->payload($part, $overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, JobItem::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
    }

    public function test_a_soft_deleted_part_cannot_be_chosen(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();
        $part->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), $this->payload($part))
            ->assertSessionHasErrors('part_id');

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_a_mechanic_submitting_a_price_fails_validation(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.parts.store', $job), $this->payload($this->stockedPart(), ['unit_price' => '1.00']))
            ->assertSessionHasErrors('unit_price');

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_front_office_may_omit_the_assignee_when_adding(): void
    {
        $job = Job::factory()->inProgress()->create();
        $payload = $this->payload($this->stockedPart());
        unset($payload['user_id']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), $payload)
            ->assertSessionHasNoErrors();

        $this->assertNull(JobItem::query()->sole()->user_id);
    }

    public function test_front_office_may_omit_the_assignee_when_editing(): void
    {
        $item = $this->partLine();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '3', 'description' => 'Edited'])
            ->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()->user_id);
        $this->assertSame('3.00', $item->fresh()->quantity);
    }

    public function test_front_office_may_omit_the_description_when_editing_a_part_line(): void
    {
        $item = $this->partLine();
        $original = $item->description;

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '3'])
            ->assertSessionHasNoErrors();

        $this->assertSame($original, $item->fresh()->description);
    }

    public function test_the_part_on_a_line_cannot_be_changed(): void
    {
        $item = $this->partLine();
        $other = $this->stockedPart();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), [
                'quantity' => '2',
                'description' => 'Edited',
                'part_id' => $other->getKey(),
            ])
            ->assertSessionHasErrors('part_id');

        $this->assertSame($item->part_id, $item->fresh()->part_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidEditProvider(): array
    {
        return [
            'a fractional quantity' => [['quantity' => '1.5'], 'quantity'],
            'a zero quantity' => [['quantity' => '0'], 'quantity'],
            'a negative quantity' => [['quantity' => '-2'], 'quantity'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidEditProvider')]
    public function test_editing_a_part_line_rejects_a_non_whole_quantity(array $overrides, string $field): void
    {
        $item = $this->partLine();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '2', 'description' => 'Edited', ...$overrides])
            ->assertSessionHasErrors($field);

        $this->assertSame('2.00', $item->fresh()->quantity);
    }

    public function test_a_labor_line_keeps_its_own_rules(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['quantity' => 1.5]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), [
                'quantity' => '2.25',
                'description' => 'Still fractional',
                'user_id' => $item->user_id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2.25', $item->fresh()->quantity);
    }

    public function test_a_labor_line_still_requires_an_assignee(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '2', 'description' => 'Edited'])
            ->assertSessionHasErrors('user_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Part $part, array $overrides = []): array
    {
        return [
            'part_id' => $part->getKey(),
            'quantity' => '1',
            'description' => 'Brake pads front',
            'user_id' => null,
            ...$overrides,
        ];
    }

    private function partLine(): JobItem
    {
        return app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(),
            User::factory()->receptionist()->create(),
            ['quantity' => 2],
        );
    }

    private function stockedPart(): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record(
            $part,
            10,
            StockMovementReason::Restock,
            User::factory()->receptionist()->create(),
        );

        return $part;
    }
}
