<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

/**
 * The policy is not the only thing standing between a terminal job and a second
 * stock return. These pin the service-layer guard, which a caller outside
 * JobItemController would otherwise walk straight past.
 */
class JobPartLineGuardTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function terminalStateProvider(): array
    {
        return [
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('terminalStateProvider')]
    public function test_the_service_refuses_to_add_a_part_line_to_a_terminal_job(string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        $part = $this->stockedPart();

        $this->expectException(JobNotAcceptingLineItems::class);

        try {
            $this->items()->addPart($job, $part, $this->actor(), ['quantity' => 2]);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
            $this->assertSame(10, $part->fresh()->quantity_in_stock);
            $this->assertStockMatchesLedger();
        }
    }

    public function test_the_service_refuses_to_delete_a_part_line_from_a_cancelled_job(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();
        $item = $this->items()->addPart($job, $part, $actor, ['quantity' => 3]);

        app(JobService::class)->cancel($job, $actor);

        $movementsAfterCancel = StockMovement::query()->count();

        $this->expectException(JobNotAcceptingLineItems::class);

        try {
            $this->items()->delete($item, $actor);
        } finally {
            $this->assertNotNull($item->fresh());
            $this->assertSame(10, $part->fresh()->quantity_in_stock);
            $this->assertSame($movementsAfterCancel, StockMovement::query()->count());
            $this->assertStockMatchesLedger();
        }
    }

    public function test_the_service_refuses_to_edit_a_part_line_on_a_cancelled_job(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();
        $item = $this->items()->addPart($job, $part, $actor, ['quantity' => 3]);

        app(JobService::class)->cancel($job, $actor);

        $this->expectException(JobNotAcceptingLineItems::class);

        try {
            $this->items()->update($item, $actor, ['quantity' => 9]);
        } finally {
            $this->assertSame('3.00', $item->fresh()->quantity);
            $this->assertSame(10, $part->fresh()->quantity_in_stock);
            $this->assertStockMatchesLedger();
        }
    }

    public function test_the_exception_names_the_job_and_the_status_that_refused_the_write(): void
    {
        $job = Job::factory()->completed()->create();

        try {
            $this->items()->addPart($job, $this->stockedPart(), $this->actor(), ['quantity' => 1]);
            $this->fail('Expected the guard to refuse.');
        } catch (JobNotAcceptingLineItems $exception) {
            $this->assertTrue($exception->job->is($job));
            $this->assertSame(JobStatus::Completed, $exception->status);
        }
    }

    public function test_a_locked_job_is_refused_by_the_lock_guard_rather_than_the_status_guard(): void
    {
        $job = Job::factory()->inProgress()->create();
        $job->locked_at = now();
        $job->save();

        $this->expectException(JobLockedException::class);

        $this->items()->addPart($job, $this->stockedPart(), $this->actor(), ['quantity' => 1]);
    }

    public function test_adding_labor_to_a_terminal_job_is_refused_too(): void
    {
        $job = Job::factory()->cancelled()->create();

        $this->expectException(JobNotAcceptingLineItems::class);

        try {
            $this->items()->addLabor($job, $this->actor(), [
                'description' => 'Sneaked in after cancellation',
                'quantity' => '1.5',
                'user_id' => User::factory()->mechanic()->create()->getKey(),
            ]);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
        }
    }

    private function stockedPart(): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record($part, 10, StockMovementReason::Restock, $this->actor());

        return $part;
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
