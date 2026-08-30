<?php

namespace Tests\Feature\JobItems;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

/**
 * The three withTrashed() calls this feature added exist for exactly one situation:
 * a part retired from the catalogue AFTER a job line already recorded it. Every
 * other test deletes the part before any line exists, which never reaches them.
 */
class JobPartLineTrashedPartTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    public function test_a_quantity_edit_still_works_once_the_part_is_retired(): void
    {
        [$item, $part] = $this->lineOnARetiredPart(quantity: 2);

        $this->items()->update($item, $this->actor(), ['quantity' => 5, 'description' => $item->description]);

        $this->assertSame(5, $part->fresh()->quantity_in_stock);
        $this->assertSame(-3, (int) StockMovement::query()->latest('id')->firstOrFail()->quantity_change);
        $this->assertStockMatchesLedger();
    }

    public function test_deleting_the_line_still_returns_stock_once_the_part_is_retired(): void
    {
        [$item, $part] = $this->lineOnARetiredPart(quantity: 4);

        $this->items()->delete($item, $this->actor());

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertNull(JobItem::query()->find($item->getKey()));
        $this->assertStockMatchesLedger();
    }

    public function test_cancelling_still_returns_stock_once_the_part_is_retired(): void
    {
        [$item, $part] = $this->lineOnARetiredPart(quantity: 3);

        app(JobService::class)->cancel($item->job()->sole(), $this->actor());

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertNotNull($item->fresh());
        $this->assertStockMatchesLedger();
    }

    public function test_the_line_keeps_naming_the_retired_part_on_the_job_page(): void
    {
        [$item] = $this->lineOnARetiredPart(quantity: 1, attributes: ['name' => 'Brake pads front']);

        $this->actingAs($this->actor())
            ->get(route('jobs.show', $item->job_id))
            ->assertOk()
            ->assertSee('Brake pads front');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: JobItem, 1: Part}
     */
    private function lineOnARetiredPart(int $quantity, array $attributes = []): array
    {
        $actor = $this->actor();
        $part = Part::factory()->create($attributes);

        app(StockService::class)->record($part, 10, StockMovementReason::Restock, $actor);

        $item = $this->items()->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $actor,
            ['quantity' => $quantity],
        );

        $part->delete();

        $this->assertTrue($part->fresh()->trashed());

        return [$item, $part];
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function items(): JobItemService
    {
        return app(JobItemService::class);
    }
}
