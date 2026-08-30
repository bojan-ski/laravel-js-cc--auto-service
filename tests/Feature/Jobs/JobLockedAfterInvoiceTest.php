<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\JobLockedException;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The lock is the guarantee the application was built around. These tests issue
 * a real invoice and then attempt every write path against the job and its
 * lines, for every role including admin.
 *
 * The line-item assertions check for JobLockedException SPECIFICALLY, not
 * merely "throws": an invoiced job is `completed`, and JobItemService's guard
 * already refuses every non-working job with JobNotAcceptingLineItems. A
 * type-blind assertion would stay green with the entire lock check deleted.
 */
class JobLockedAfterInvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ROLES = ['mechanic', 'receptionist', 'admin'];

    private const TRANSITIONS = ['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'];

    private Job $job;

    private JobItem $laborLine;

    private JobItem $partLine;

    private Part $part;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = Job::factory()->completed()->create();

        $this->laborLine = JobItem::factory()->forJob($this->job)->create([
            'quantity' => 1.50,
            'unit_price' => 3000,
        ]);

        $this->part = Part::factory()->create(['quantity_in_stock' => 10, 'unit_price' => 250]);
        $this->partLine = JobItem::factory()->forJob($this->job)->forPart($this->part)->create([
            'quantity' => 2,
            'user_id' => null,
        ]);

        app(InvoiceService::class)->issue($this->job, User::factory()->receptionist()->create());

        $this->job->refresh();
        $this->assertNotNull($this->job->locked_at, 'The fixture must actually be locked.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function roleAndTransitionProvider(): array
    {
        $cases = [];

        foreach (self::ROLES as $role) {
            foreach (self::TRANSITIONS as $transition) {
                $cases["{$transition} as {$role}"] = [$role, $transition];
            }
        }

        return $cases;
    }

    #[DataProvider('roleAndTransitionProvider')]
    public function test_every_transition_is_refused_on_a_locked_job(string $role, string $transition): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->patch(route("jobs.{$transition}", $this->job))
            ->assertForbidden();

        $this->assertNotNull($this->job->fresh()->locked_at);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleProvider(): array
    {
        return array_combine(
            self::ROLES,
            array_map(fn (string $role): array => [$role], self::ROLES),
        );
    }

    #[DataProvider('roleProvider')]
    public function test_adding_a_labor_line_throws_the_lock_exception(string $role): void
    {
        $actor = User::factory()->{$role}()->create();

        $this->expectException(JobLockedException::class);

        app(JobItemService::class)->addLabor($this->job, $actor, [
            'description' => 'Smuggled in after invoicing',
            'quantity' => '1.00',
        ]);
    }

    #[DataProvider('roleProvider')]
    public function test_adding_a_part_line_throws_the_lock_exception(string $role): void
    {
        $actor = User::factory()->{$role}()->create();

        $this->expectException(JobLockedException::class);

        app(JobItemService::class)->addPart($this->job, $this->part, $actor, ['quantity' => '1']);
    }

    #[DataProvider('roleProvider')]
    public function test_editing_a_line_throws_the_lock_exception(string $role): void
    {
        $actor = User::factory()->{$role}()->create();

        $this->expectException(JobLockedException::class);

        app(JobItemService::class)->update($this->laborLine, $actor, ['quantity' => '9.00']);
    }

    #[DataProvider('roleProvider')]
    public function test_deleting_a_line_throws_the_lock_exception(string $role): void
    {
        $actor = User::factory()->{$role}()->create();

        $this->expectException(JobLockedException::class);

        app(JobItemService::class)->delete($this->laborLine, $actor);
    }

    public function test_a_line_item_saved_directly_is_refused_by_the_model_guard(): void
    {
        $this->laborLine->description = 'Edited past the service';

        $this->expectException(JobLockedException::class);

        $this->laborLine->save();
    }

    public function test_a_line_item_deleted_directly_is_refused_by_the_model_guard(): void
    {
        $this->expectException(JobLockedException::class);

        $this->partLine->delete();
    }

    public function test_the_job_itself_is_refused_by_the_model_guard(): void
    {
        $this->job->complaint = 'Rewritten after billing';

        $this->expectException(JobLockedException::class);

        $this->job->save();
    }

    public function test_no_stock_movement_can_be_produced_by_a_locked_job(): void
    {
        $before = StockMovement::query()->count();
        $balanceBefore = $this->part->fresh()->quantity_in_stock;
        $actor = User::factory()->admin()->create();
        $service = app(JobItemService::class);

        foreach ([
            fn () => $service->addPart($this->job, $this->part, $actor, ['quantity' => '3']),
            fn () => $service->update($this->partLine, $actor, ['quantity' => '7']),
            fn () => $service->delete($this->partLine, $actor),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected JobLockedException.');
            } catch (JobLockedException) {
                // Every stock path in spec 16 runs through a line-item write.
            }
        }

        $this->assertSame($before, StockMovement::query()->count());
        $this->assertSame($balanceBefore, $this->part->fresh()->quantity_in_stock);
    }

    public function test_the_line_items_survive_every_refused_attempt(): void
    {
        $this->assertSame(2, $this->job->items()->count());
        $this->assertSame('1.50', $this->laborLine->fresh()->quantity);
        $this->assertSame('2.00', $this->partLine->fresh()->quantity);
    }
}
