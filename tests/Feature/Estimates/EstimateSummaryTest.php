<?php

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstimateSummaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): EstimateService
    {
        return app(EstimateService::class);
    }

    private function jobWithLine(string $unitPrice = '1000.00'): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => $unitPrice]);

        return $job->load('items');
    }

    private function liveTotal(Job $job): string
    {
        return app(InvoiceService::class)->totalsFor($job)['total'];
    }

    public function test_a_job_without_an_estimate_has_no_summary(): void
    {
        $this->assertNull($this->service()->summaryFor($this->jobWithLine()));
    }

    public function test_a_pending_estimate_warns_not_approved(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->pending()->forJob($job)->create();

        $summary = $this->service()->summaryFor($job);

        $this->assertTrue($summary['estimate']->is($estimate));
        $this->assertSame('not_approved', $summary['warning']);
        $this->assertNull($summary['over_by']);
    }

    public function test_a_declined_estimate_warns_declined(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->declined()->forJob($job)->create();

        $this->assertSame('declined', $this->service()->summaryFor($job)['warning']);
    }

    public function test_an_approved_estimate_below_the_live_total_warns_over_with_the_difference(): void
    {
        $job = $this->jobWithLine('1000.00');
        Estimate::factory()->approved()->forJob($job)->create(['total' => '1000.00']);

        $summary = $this->service()->summaryFor($job);

        $this->assertSame('1200.00', $this->liveTotal($job));
        $this->assertSame('over', $summary['warning']);
        $this->assertSame('200.00', $summary['over_by']);
    }

    public function test_an_approved_estimate_equal_to_the_live_total_raises_nothing(): void
    {
        $job = $this->jobWithLine('1000.00');
        Estimate::factory()->approved()->forJob($job)->create(['total' => '1200.00']);

        $summary = $this->service()->summaryFor($job);

        $this->assertNull($summary['warning']);
        $this->assertNull($summary['over_by']);
    }

    public function test_an_approved_estimate_above_the_live_total_raises_nothing(): void
    {
        $job = $this->jobWithLine('1000.00');
        Estimate::factory()->approved()->forJob($job)->create(['total' => '5000.00']);

        $this->assertNull($this->service()->summaryFor($job)['warning']);
    }

    public function test_the_comparison_uses_the_rate_in_config_today(): void
    {
        $job = $this->jobWithLine('1000.00');
        Estimate::factory()->approved()->forJob($job)->create(['total' => '1200.00']);

        config(['shop.vat_rate' => '25.00']);

        $summary = $this->service()->summaryFor($job);
        $this->assertSame('over', $summary['warning']);
        $this->assertSame('50.00', $summary['over_by']);
    }

    public function test_the_summary_reads_the_newest_estimate_only(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->declined()->forJob($job)->create();
        $current = Estimate::factory()->approved()->forJob($job)->create(['total' => '9999.00']);

        $summary = $this->service()->summaryFor($job);

        $this->assertTrue($summary['estimate']->is($current));
        $this->assertNull($summary['warning']);
    }

    public function test_the_warning_holds_on_cancelled_and_invoiced_jobs(): void
    {
        foreach (['cancelled', 'locked'] as $state) {
            $job = Job::factory()->{$state === 'locked' ? 'completed' : $state}()->create();
            JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => '1000.00']);
            Estimate::factory()->approved()->forJob($job)->create(['total' => '100.00']);

            if ($state === 'locked') {
                DB::table('jobs')->where('id', $job->getKey())->update(['locked_at' => now()]);
                $job->refresh();
            }

            $this->assertSame('over', $this->service()->summaryFor($job->load('items'))['warning'], $state);
        }
    }

    public function test_the_summary_reads_the_estimate_in_one_query_when_the_lines_are_loaded(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->approved()->forJob($job)->create();

        DB::enableQueryLog();
        $this->service()->summaryFor($job);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_history_lists_every_estimate_on_the_job_newest_first_with_only_the_listed_columns(): void
    {
        $job = $this->jobWithLine();
        $first = Estimate::factory()->superseded()->forJob($job)->create();
        $second = Estimate::factory()->declined()->forJob($job)->create();
        $third = Estimate::factory()->forJob($job)->create();
        Estimate::factory()->create();

        $history = $this->service()->historyFor($first);

        $this->assertSame([$third->getKey(), $second->getKey(), $first->getKey()], $history->modelKeys());
        $this->assertEqualsCanonicalizing(
            ['id', 'job_id', 'number', 'status', 'total', 'created_at'],
            array_keys($history->first()->getAttributes()),
        );
    }

    public function test_the_estimate_on_the_job_page_reaches_the_view_for_front_office(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertViewHas('showEstimate', true)
            ->assertViewHas('estimate', fn (?array $summary): bool => $summary !== null && $summary['estimate']->is($estimate));
    }
}
