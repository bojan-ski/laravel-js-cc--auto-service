<?php

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstimateJobPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function jobWithLine(string $state = 'inProgress'): Job
    {
        $job = Job::factory()->{$state}()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => '1000.00']);

        return $job;
    }

    private function page(Job $job, string $role = 'receptionist'): string
    {
        return $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function pageWithQueries(Job $job, string $role): array
    {
        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $html = $this->actingAs($user)->get(route('jobs.show', $job))->assertOk()->getContent();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return [$html, $queries];
    }

    public function test_a_job_without_an_estimate_says_so_offers_create_and_warns_nothing(): void
    {
        $job = $this->jobWithLine();
        $html = $this->page($job);

        $this->assertStringContainsString(__('estimates.summary.label'), $html);
        $this->assertStringContainsString(__('estimates.summary.none'), $html);
        $this->assertStringContainsString('action="'.route('jobs.estimates.store', $job).'"', $html);
        $this->assertStringContainsString('aria-label="'.e(__('estimates.create.action_for', ['reference' => $job->reference])).'"', $html);
        $this->assertStringNotContainsString('&#9888;', $html);
        $this->assertStringNotContainsString('data-confirm="'.e(__('estimates.create.revise_confirm', ['number' => 'x'])), $html);
    }

    public function test_a_pending_estimate_shows_its_number_and_warns_not_approved_and_revise_asks_first(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->pending()->forJob($job)->create();
        $html = $this->page($job);

        $this->assertStringContainsString($estimate->number, $html);
        $this->assertStringContainsString(__('estimates.statuses.pending'), $html);
        $this->assertStringContainsString(e(__('estimates.warning.not_approved', ['number' => $estimate->number])), $html);
        $this->assertStringContainsString('aria-label="'.e(__('estimates.summary.view_for', ['number' => $estimate->number])).'"', $html);
        $this->assertStringContainsString('href="'.route('estimates.show', $estimate).'"', $html);
        $this->assertStringContainsString('data-confirm="'.e(__('estimates.create.revise_confirm', ['number' => $estimate->number])).'"', $html);
        $this->assertStringContainsString(__('estimates.create.revise'), $html);
        $this->assertStringStartsWith(__('estimates.create.revise'), __('estimates.create.revise_for', ['reference' => $job->reference]));
        $this->assertStringStartsWith(__('estimates.summary.view'), __('estimates.summary.view_for', ['number' => $estimate->number]));
    }

    public function test_an_approved_estimate_within_the_live_total_shows_its_method_and_no_warning(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->approved()->forJob($job)->create(['total' => '1200.00']);
        $html = $this->page($job);

        $this->assertStringContainsString(e(__('estimates.summary.method', ['method' => __('estimates.decision_methods.phone')])), $html);
        $this->assertStringNotContainsString('&#9888;', $html);
    }

    public function test_an_approved_estimate_below_the_live_total_warns_with_the_difference(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->approved()->forJob($job)->create(['total' => '1000.00']);

        $this->assertStringContainsString(
            e(__('estimates.warning.over', ['amount' => '200.00', 'number' => $estimate->number])),
            $this->page($job),
        );
    }

    public function test_a_declined_estimate_warns_declined_and_offers_create_not_revise(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->declined()->forJob($job)->create();
        $html = $this->page($job);

        $this->assertStringContainsString(e(__('estimates.warning.declined', ['number' => $estimate->number])), $html);
        $this->assertStringContainsString(__('estimates.create.action'), $html);
        $this->assertStringNotContainsString('data-confirm="'.e(__('estimates.create.revise_confirm', ['number' => $estimate->number])).'"', $html);
    }

    public function test_the_warning_shows_on_an_invoiced_job_but_the_create_control_does_not(): void
    {
        $job = $this->jobWithLine('completed');
        $estimate = Estimate::factory()->approved()->forJob($job)->create(['total' => '100.00']);
        DB::table('jobs')->where('id', $job->getKey())->update(['locked_at' => now()]);

        $html = $this->page($job);

        $this->assertStringContainsString(e(__('estimates.warning.over', ['amount' => '1,100.00', 'number' => $estimate->number])), $html);
        $this->assertStringNotContainsString(route('jobs.estimates.store', $job), $html);
    }

    public function test_a_lineless_job_offers_no_create_control(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->assertStringNotContainsString(route('jobs.estimates.store', $job), $this->page($job));
    }

    public function test_a_mechanic_sees_no_estimate_fact_and_the_page_reads_no_estimate_table(): void
    {
        $job = $this->jobWithLine();
        $estimate = Estimate::factory()->approved()->forJob($job)->create(['total' => '100.00']);

        [$html, $queries] = $this->pageWithQueries($job, 'mechanic');

        $this->assertDoesNotMatchRegularExpression('/<dt\b[^>]*>\s*'.preg_quote(__('estimates.summary.label'), '/').'\s*<\/dt>/', $html);
        $this->assertStringNotContainsString($estimate->number, $html);
        $this->assertStringNotContainsString(route('jobs.estimates.store', $job), $html);
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'estimate'))), implode("\n", $queries));
    }

    public function test_a_front_office_page_does_render_the_fact_so_the_mechanic_check_can_fail(): void
    {
        $this->assertMatchesRegularExpression(
            '/<dt\b[^>]*>\s*'.preg_quote(__('estimates.summary.label'), '/').'\s*<\/dt>/',
            $this->page($this->jobWithLine()),
        );
    }

    public function test_a_mechanic_receives_no_summary_in_the_view(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertViewHas('showEstimate', false)
            ->assertViewHas('estimate', fn (mixed $value): bool => $value === null);
    }

    public function test_the_front_office_job_page_reads_a_pinned_number_of_queries(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->approved()->forJob($job)->create();

        [, $queries] = $this->pageWithQueries($job, 'receptionist');

        $this->assertCount(self::FRONT_OFFICE_QUERIES, $queries, implode("\n", $queries));
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`')));
    }

    /**
     * Measured, not predicted: the estimate adds exactly one read (the
     * latestOfMany join) to the front-office job page.
     */
    private const FRONT_OFFICE_QUERIES = 10;
}
