<?php

namespace Tests\Feature\Estimates;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\InvalidEstimateTransition;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimateModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nothing_on_either_model_is_mass_assignable(): void
    {
        $this->assertSame([], (new Estimate)->getFillable());
        $this->assertSame([], (new EstimateLine)->getFillable());
        $this->assertTrue((new Estimate)->isGuarded('status'));
        $this->assertTrue((new EstimateLine)->isGuarded('line_total'));
    }

    public function test_a_new_estimate_starts_pending(): void
    {
        $this->assertSame(EstimateStatus::Pending, (new Estimate)->status);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function frozenColumns(): array
    {
        return [
            'total' => ['total', '1.00'],
            'subtotal' => ['subtotal', '1.00'],
            'tax_rate' => ['tax_rate', '10.00'],
            'number' => ['number', 'EST-1999-0001'],
            'job' => ['job_id', 'another job'],
            'creator' => ['created_by', 'another user'],
        ];
    }

    #[DataProvider('frozenColumns')]
    public function test_a_frozen_column_cannot_change(string $column, mixed $value): void
    {
        $estimate = Estimate::factory()->create();

        $estimate->{$column} = match ($value) {
            'another job' => Job::factory()->inProgress()->create()->getKey(),
            'another user' => User::factory()->receptionist()->create()->getKey(),
            default => $value,
        };

        $this->expectException(InvalidEstimateTransition::class);

        $estimate->save();
    }

    public function test_the_decision_columns_and_the_email_date_may_change_on_an_open_estimate(): void
    {
        $estimate = Estimate::factory()->create();
        $recorder = User::factory()->receptionist()->create();

        $estimate->status = EstimateStatus::Approved;
        $estimate->decision_method = DecisionMethod::Email;
        $estimate->decided_at = now();
        $estimate->decided_by = $recorder->getKey();
        $estimate->decision_note = 'Agreed by email.';
        $estimate->emailed_at = now();
        $estimate->save();

        $estimate->status = EstimateStatus::Superseded;
        $estimate->superseded_at = now();
        $estimate->save();

        $this->assertTrue($estimate->fresh()->isSuperseded());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalStates(): array
    {
        return ['declined' => ['declined'], 'superseded' => ['superseded']];
    }

    #[DataProvider('terminalStates')]
    public function test_a_terminal_estimate_refuses_even_a_permitted_column(string $state): void
    {
        $estimate = Estimate::factory()->{$state}()->create();

        $estimate->emailed_at = now();

        $this->expectException(InvalidEstimateTransition::class);

        $estimate->save();
    }

    public function test_a_declined_estimate_cannot_be_redecided(): void
    {
        $estimate = Estimate::factory()->declined()->create();

        $estimate->status = EstimateStatus::Approved;

        $this->expectException(InvalidEstimateTransition::class);

        $estimate->save();
    }

    public function test_an_estimate_is_never_deleted(): void
    {
        $estimate = Estimate::factory()->create();

        try {
            $estimate->delete();
            $this->fail('Expected InvalidEstimateTransition.');
        } catch (InvalidEstimateTransition) {
            $this->assertModelExists($estimate);
        }
    }

    public function test_a_partial_select_refuses_to_save_rather_than_read_as_open(): void
    {
        $estimate = Estimate::factory()->declined()->create();

        $partial = Estimate::query()->select(['id', 'emailed_at'])->findOrFail($estimate->getKey());
        $partial->emailed_at = now();

        $this->expectException(InvalidEstimateTransition::class);

        $partial->save();
    }

    public function test_a_line_is_never_updated(): void
    {
        $line = EstimateLine::factory()->create();

        $line->description = 'Rewritten';

        $this->expectException(InvalidEstimateTransition::class);

        $line->save();
    }

    public function test_a_line_is_never_deleted(): void
    {
        $line = EstimateLine::factory()->create();

        try {
            $line->delete();
            $this->fail('Expected InvalidEstimateTransition.');
        } catch (InvalidEstimateTransition) {
            $this->assertModelExists($line);
        }
    }

    public function test_the_line_total_is_the_stored_column_not_a_recomputation(): void
    {
        $line = EstimateLine::factory()->create([
            'quantity' => '2.00',
            'unit_price' => '100.00',
            'discount_percent' => '0.00',
            'line_total' => '150.00',
        ]);

        $this->assertSame('150.00', $line->fresh()->lineTotal());
    }

    public function test_line_predicates_and_the_discount_label(): void
    {
        $line = EstimateLine::factory()->create(['discount_percent' => '12.50']);
        $plain = EstimateLine::factory()->create(['discount_percent' => '0.00']);

        $this->assertTrue($line->isLabor());
        $this->assertTrue($line->hasDiscount());
        $this->assertSame('12.5', $line->discountPercentLabel());
        $this->assertFalse($plain->hasDiscount());
        $this->assertSame('0', $plain->discountPercentLabel());
    }

    public function test_status_predicates_follow_the_lifecycle(): void
    {
        $pending = Estimate::factory()->pending()->make();
        $approved = Estimate::factory()->approved()->make();
        $declined = Estimate::factory()->declined()->make();
        $superseded = Estimate::factory()->superseded()->make();

        $this->assertTrue($pending->canBeDecided());
        $this->assertTrue($pending->canBeEmailed());
        $this->assertFalse($approved->canBeDecided());
        $this->assertTrue($approved->canBeEmailed());
        $this->assertFalse($declined->canBeDecided());
        $this->assertFalse($declined->canBeEmailed());
        $this->assertFalse($superseded->canBeDecided());
        $this->assertFalse($superseded->canBeEmailed());

        $this->assertTrue(EstimateStatus::Pending->isOpen());
        $this->assertTrue(EstimateStatus::Approved->isOpen());
        $this->assertTrue(EstimateStatus::Declined->isTerminal());
        $this->assertTrue(EstimateStatus::Superseded->isTerminal());
    }

    public function test_tax_rate_label_and_discount_flag(): void
    {
        $estimate = Estimate::factory()->make(['tax_rate' => '20.00', 'discount_total' => '0.00']);
        $discounted = Estimate::factory()->make(['tax_rate' => '17.50', 'discount_total' => '4.20']);

        $this->assertSame('20', $estimate->taxRateLabel());
        $this->assertFalse($estimate->hasDiscount());
        $this->assertSame('17.5', $discounted->taxRateLabel());
        $this->assertTrue($discounted->hasDiscount());
    }

    public function test_relations_resolve_soft_deleted_users_and_order_lines_by_position(): void
    {
        $creator = User::factory()->receptionist()->create();
        $estimate = Estimate::factory()->approved()->create(['created_by' => $creator->getKey()]);
        EstimateLine::factory()->for($estimate)->create(['position' => 2, 'description' => 'Second']);
        EstimateLine::factory()->for($estimate)->create(['position' => 1, 'description' => 'First']);
        $creator->delete();
        $estimate->decidedBy->delete();

        $fresh = $estimate->fresh();

        $this->assertTrue($fresh->createdBy->is($creator));
        $this->assertNotNull($fresh->decidedBy);
        $this->assertSame(['First', 'Second'], $fresh->lines->pluck('description')->all());
    }

    public function test_the_job_can_be_estimated_only_while_working_unlocked_and_with_a_line(): void
    {
        $working = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($working)->create();
        $lineless = Job::factory()->inProgress()->create();
        $completed = Job::factory()->completed()->create();
        JobItem::factory()->forJob($completed)->create();
        $locked = Job::factory()->completed()->locked()->create();

        $this->assertTrue($working->canBeEstimated());
        $this->assertFalse($lineless->canBeEstimated());
        $this->assertFalse($completed->canBeEstimated());
        $this->assertFalse($locked->canBeEstimated());
    }

    public function test_the_current_estimate_is_the_newest_by_id(): void
    {
        $job = Job::factory()->inProgress()->create();
        Estimate::factory()->superseded()->forJob($job)->create();
        $newest = Estimate::factory()->forJob($job)->create();

        $this->assertTrue($job->currentEstimate->is($newest));
        $this->assertSame(2, $job->estimates()->count());
    }
}
