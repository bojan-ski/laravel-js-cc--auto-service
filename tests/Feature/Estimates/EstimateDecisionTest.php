<?php

namespace Tests\Feature\Estimates;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\InvalidEstimateTransition;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\User;
use App\Services\EstimateService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimateDecisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): EstimateService
    {
        return app(EstimateService::class);
    }

    /**
     * @return array<string, array{EstimateStatus}>
     */
    public static function decisions(): array
    {
        return ['approved' => [EstimateStatus::Approved], 'declined' => [EstimateStatus::Declined]];
    }

    #[DataProvider('decisions')]
    public function test_a_decision_sets_the_status_and_the_four_decision_columns_and_nothing_else(EstimateStatus $decision): void
    {
        $estimate = Estimate::factory()->create();
        $before = $estimate->fresh()->getAttributes();
        $desk = User::factory()->receptionist()->create();
        $this->travel(1)->minutes();
        $this->freezeSecond();

        $this->service()->decide($estimate, $decision, DecisionMethod::InPerson, 'Agreed at the counter.', $desk);

        $after = $estimate->fresh();
        $this->assertSame($decision, $after->status);
        $this->assertSame(DecisionMethod::InPerson, $after->decision_method);
        $this->assertEquals(now(), $after->decided_at);
        $this->assertSame($desk->getKey(), $after->decided_by);
        $this->assertSame('Agreed at the counter.', $after->decision_note);

        $changed = array_keys(array_diff_assoc($after->getAttributes(), $before));
        sort($changed);
        $this->assertSame(['decided_at', 'decided_by', 'decision_method', 'decision_note', 'status', 'updated_at'], $changed);
    }

    public function test_the_callers_instance_reflects_the_decision(): void
    {
        $estimate = Estimate::factory()->create();

        $this->service()->decide($estimate, EstimateStatus::Approved, DecisionMethod::Phone, null, User::factory()->receptionist()->create());

        $this->assertTrue($estimate->isApproved());
        $this->assertNull($estimate->decision_note);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function jobStates(): array
    {
        return ['completed' => ['completed'], 'locked' => ['locked'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('jobStates')]
    public function test_a_pending_estimate_can_be_decided_whatever_became_of_its_job(string $state): void
    {
        $estimate = Estimate::factory()->forJob(Job::factory()->{$state}()->create())->create();

        $this->service()->decide($estimate, EstimateStatus::Approved, DecisionMethod::Email, null, User::factory()->receptionist()->create());

        $this->assertTrue($estimate->fresh()->isApproved());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function undecidable(): array
    {
        return ['approved' => ['approved'], 'declined' => ['declined'], 'superseded' => ['superseded']];
    }

    #[DataProvider('undecidable')]
    public function test_only_a_pending_estimate_can_be_decided(string $state): void
    {
        $estimate = Estimate::factory()->{$state}()->create();
        $before = $estimate->fresh()->getAttributes();

        try {
            $this->service()->decide($estimate, EstimateStatus::Declined, DecisionMethod::Phone, null, User::factory()->receptionist()->create());
            $this->fail('Expected InvalidEstimateTransition.');
        } catch (InvalidEstimateTransition) {
            $this->assertSame($before, $estimate->fresh()->getAttributes());
        }
    }

    public function test_the_refusal_reads_the_locked_row_not_the_callers_stale_copy(): void
    {
        $estimate = Estimate::factory()->create();
        DB::table('estimates')->where('id', $estimate->getKey())->update(['status' => 'superseded', 'superseded_at' => now()]);

        $this->assertTrue($estimate->isPending(), 'The caller still holds the stale pending copy.');
        $this->expectException(InvalidEstimateTransition::class);

        $this->service()->decide($estimate, EstimateStatus::Approved, DecisionMethod::Phone, null, User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, array{EstimateStatus}>
     */
    public static function nonDecisions(): array
    {
        return ['pending' => [EstimateStatus::Pending], 'superseded' => [EstimateStatus::Superseded]];
    }

    #[DataProvider('nonDecisions')]
    public function test_a_status_that_is_not_a_decision_is_refused(EstimateStatus $status): void
    {
        $estimate = Estimate::factory()->create();

        try {
            $this->service()->decide($estimate, $status, DecisionMethod::Phone, null, User::factory()->receptionist()->create());
            $this->fail('Expected InvalidEstimateTransition.');
        } catch (InvalidEstimateTransition) {
            $this->assertTrue($estimate->fresh()->isPending());
        }
    }

    public function test_the_estimate_row_is_locked_before_the_decision_is_written(): void
    {
        $estimate = Estimate::factory()->create();
        $desk = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->service()->decide($estimate, EstimateStatus::Approved, DecisionMethod::Phone, null, $desk);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lock = array_key_first(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`') && str_contains($sql, 'for update')));
        $write = array_key_first(array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'update `estimates`')));

        $this->assertNotNull($lock, "No locking read:\n".implode("\n", $queries));
        $this->assertNotNull($write);
        $this->assertLessThan($write, $lock);
    }
}
