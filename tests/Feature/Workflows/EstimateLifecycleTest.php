<?php

namespace Tests\Feature\Workflows;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\InvalidEstimateTransition;
use App\Mail\EstimateMail;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EstimateService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The estimate lifecycle as one state × action table (spec 33's machine, from
 * the overview's Estimate Lifecycle).
 *
 * Every cell is also asserted somewhere in spec 33's own files. What this test
 * adds is that the states come from EstimateStatus::cases() and the outcomes
 * from one legal-move map, so a status added later fails here until the map
 * names it — none of spec 33's provider lists is derived from the enum.
 *
 * A refused cell asserts the exception, the stored row byte-for-byte, and that
 * no mail was queued. A permitted cell asserts the new status, the columns the
 * transition sets, and that no other column moved. The clock is moved a minute
 * before acting, so `updated_at` changes deterministically and is named.
 */
class EstimateLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array ACTIONS = ['approve', 'decline', 'revise', 'email'];

    /**
     * From state → action → the acted-on estimate's status afterwards, or null
     * when the action is refused. Revise always succeeds on a working job: it
     * creates a new estimate and supersedes the acted-on one only while that
     * one is open, so on a closed estimate the row is left as it was.
     */
    private const array LEGAL_MOVES = [
        'pending' => ['approve' => 'approved', 'decline' => 'declined', 'revise' => 'superseded', 'email' => 'pending'],
        'approved' => ['approve' => null, 'decline' => null, 'revise' => 'superseded', 'email' => 'approved'],
        'declined' => ['approve' => null, 'decline' => null, 'revise' => 'declined', 'email' => null],
        'superseded' => ['approve' => null, 'decline' => null, 'revise' => 'superseded', 'email' => null],
    ];

    /**
     * The columns each permitted action writes on the acted-on row, besides
     * `updated_at`. A revise on a closed estimate writes nothing at all.
     */
    private const array WRITES = [
        'approve' => ['decided_at', 'decided_by', 'decision_method', 'decision_note', 'status'],
        'decline' => ['decided_at', 'decided_by', 'decision_method', 'decision_note', 'status'],
        'revise' => ['status', 'superseded_at'],
        'email' => ['emailed_at'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @return array<string, array{EstimateStatus, string}>
     */
    public static function cells(): array
    {
        $cells = [];

        foreach (EstimateStatus::cases() as $status) {
            foreach (self::ACTIONS as $action) {
                $cells["{$status->value} × {$action}"] = [$status, $action];
            }
        }

        return $cells;
    }

    public function test_the_map_names_every_status_and_every_action(): void
    {
        $this->assertSame(
            array_map(fn (EstimateStatus $status): string => $status->value, EstimateStatus::cases()),
            array_keys(self::LEGAL_MOVES),
            'The legal-move map must name every EstimateStatus case, in enum order.',
        );

        foreach (self::LEGAL_MOVES as $from => $row) {
            $this->assertSame(self::ACTIONS, array_keys($row), "The {$from} row must name the four actions.");
        }
    }

    public function test_the_table_holds_sixteen_cells(): void
    {
        $this->assertCount(16, self::cells());
    }

    #[DataProvider('cells')]
    public function test_the_cell_behaves_as_the_map_says(EstimateStatus $from, string $action): void
    {
        $desk = User::factory()->receptionist()->create();
        $job = $this->workingJob();
        $estimate = $this->estimateIn($from, $job);
        $before = $this->row($estimate);
        $estimatesBefore = Estimate::query()->where('job_id', $job->getKey())->count();

        $this->travel(1)->minutes();
        $this->freezeSecond();

        $expected = self::LEGAL_MOVES[$from->value][$action];

        if ($expected === null) {
            try {
                $this->act($action, $estimate, $desk);
                $this->fail("{$from->value} × {$action} must be refused.");
            } catch (InvalidEstimateTransition) {
                // the refusal is the expected outcome
            }

            $this->assertSame($before, $this->row($estimate), "{$from->value} × {$action}: a refused action changed the stored row.");
            $this->assertSame($estimatesBefore, Estimate::query()->where('job_id', $job->getKey())->count());
            Mail::assertNothingOutgoing();

            return;
        }

        $this->act($action, $estimate, $desk);
        $after = $this->row($estimate);

        $this->assertSame($expected, $after['status'], "{$from->value} × {$action}");
        $this->assertSame($this->expectedChanges($from, $action), $this->changedColumns($before, $after), "{$from->value} × {$action}: the wrong columns moved.");
        $this->assertWrittenValues($action, $from, $after, $desk);

        if ($action === 'revise') {
            $newest = Estimate::query()->where('job_id', $job->getKey())->orderByDesc('id')->firstOrFail();

            $this->assertSame($estimatesBefore + 1, Estimate::query()->where('job_id', $job->getKey())->count());
            $this->assertNotSame($estimate->getKey(), $newest->getKey());
            $this->assertSame(EstimateStatus::Pending, $newest->status);
        } else {
            $this->assertSame($estimatesBefore, Estimate::query()->where('job_id', $job->getKey())->count());
        }

        if ($action === 'email') {
            Mail::assertQueuedCount(1);
            Mail::assertQueued(EstimateMail::class, fn (EstimateMail $mail): bool => $mail->hasTo('lifecycle@example.test'));
        } else {
            Mail::assertNothingOutgoing();
        }
    }

    private function act(string $action, Estimate $estimate, User $desk): void
    {
        $service = app(EstimateService::class);

        match ($action) {
            'approve' => $service->decide($estimate, EstimateStatus::Approved, DecisionMethod::Phone, 'Agreed on the phone.', $desk),
            'decline' => $service->decide($estimate, EstimateStatus::Declined, DecisionMethod::Phone, 'Too dear.', $desk),
            'revise' => $service->create($estimate->job()->firstOrFail(), $desk),
            'email' => $service->email($estimate),
        };
    }

    /**
     * @return list<string>
     */
    private function expectedChanges(EstimateStatus $from, string $action): array
    {
        if ($action === 'revise' && ! $from->isOpen()) {
            return [];
        }

        $columns = [...self::WRITES[$action], 'updated_at'];
        sort($columns);

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function assertWrittenValues(string $action, EstimateStatus $from, array $after, User $desk): void
    {
        $now = now()->format('Y-m-d H:i:s');

        match ($action) {
            'approve', 'decline' => $this->assertSame(
                ['decision_method' => 'phone', 'decided_at' => $now, 'decided_by' => $desk->getKey(), 'decision_note' => $action === 'approve' ? 'Agreed on the phone.' : 'Too dear.'],
                array_intersect_key($after, array_flip(['decision_method', 'decided_at', 'decided_by', 'decision_note'])),
            ),
            'revise' => $from->isOpen()
                ? $this->assertSame($now, $after['superseded_at'])
                : null,
            'email' => $this->assertSame($now, $after['emailed_at']),
        };
    }

    private function workingJob(): Job
    {
        $customer = Customer::factory()->create(['email' => 'lifecycle@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->inProgress()->create(['customer_id' => $customer->getKey(), 'vehicle_id' => $vehicle->getKey()]);
        JobItem::factory()->forJob($job)->create();

        return $job;
    }

    private function estimateIn(EstimateStatus $status, Job $job): Estimate
    {
        $factory = Estimate::factory()->forJob($job)->withLines();

        return match ($status) {
            EstimateStatus::Pending => $factory->pending()->create(),
            EstimateStatus::Approved => $factory->approved()->create(),
            EstimateStatus::Declined => $factory->declined()->create(),
            EstimateStatus::Superseded => $factory->superseded()->create(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Estimate $estimate): array
    {
        return (array) DB::table('estimates')->where('id', $estimate->getKey())->first();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedColumns(array $before, array $after): array
    {
        $changed = array_keys(array_filter($after, fn (mixed $value, string $column): bool => $before[$column] !== $value, ARRAY_FILTER_USE_BOTH));
        sort($changed);

        return $changed;
    }
}
