<?php

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimateIssuePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function completedJob(): Job
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => '1000.00']);

        return $job;
    }

    private function issuePage(Job $job): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.invoice.create', $job))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return array<string, array{string, array<string, string>, string}>
     */
    public static function warnings(): array
    {
        return [
            'pending' => ['pending', [], 'not_approved'],
            'declined' => ['declined', [], 'declined'],
            'exceeded' => ['approved', ['total' => '1000.00'], 'over'],
        ];
    }

    #[DataProvider('warnings')]
    public function test_the_warning_sits_in_a_note_directly_above_the_totals(string $state, array $attributes, string $warning): void
    {
        $job = $this->completedJob();
        $estimate = Estimate::factory()->{$state}()->forJob($job)->create($attributes);

        $html = $this->issuePage($job);
        $text = match ($warning) {
            'over' => __('estimates.warning.over', ['amount' => '200.00', 'number' => $estimate->number]),
            default => __("estimates.warning.{$warning}", ['number' => $estimate->number]),
        };

        $this->assertMatchesRegularExpression(
            '/<div class="border border-status-unpaid bg-status-unpaid\/10 p-4" role="note">\s*<p[^>]*>.*?'.preg_quote(e($text), '/').'\s*<\/p>\s*<\/div>\s*<dl\b/s',
            $html,
        );
    }

    public function test_no_estimate_and_an_approval_within_the_total_raise_no_note(): void
    {
        $job = $this->completedJob();
        $baseline = substr_count($this->issuePage($job), 'role="note"');

        Estimate::factory()->approved()->forJob($job)->create(['total' => '1200.00']);

        $this->assertSame($baseline, substr_count($this->issuePage($job), 'role="note"'));
        $this->assertStringNotContainsString('&#9888;', $this->issuePage($job));
    }

    #[DataProvider('warnings')]
    public function test_issuing_is_never_refused_because_of_the_estimate(string $state, array $attributes): void
    {
        $job = $this->completedJob();
        Estimate::factory()->{$state}()->forJob($job)->create($attributes);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job))
            ->assertRedirect(route('invoices.show', Invoice::query()->sole()));

        $this->assertTrue($job->fresh()->isLocked());
    }
}
