<?php

namespace Tests\Feature\CheckIns;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The job page's one-line Check-in fact. Asserted inside the details list
 * only, so a word that also appears elsewhere on the page cannot satisfy it.
 */
class CheckInSummaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_working_job_with_no_sheet_offers_to_record_one(): void
    {
        $job = Job::factory()->received()->create();

        $line = $this->summaryFor($job);

        $this->assertStringContainsString(e(__('check_ins.summary.not_recorded')), $line);
        $this->assertStringContainsString('href="'.route('jobs.check-in.show', $job).'"', $line);
        $this->assertStringContainsString(e(__('check_ins.summary.record')), $line);
    }

    public function test_a_closed_job_with_no_sheet_has_no_link(): void
    {
        foreach ([Job::factory()->cancelled()->create(), Job::factory()->locked()->create()] as $job) {
            $line = $this->summaryFor($job);

            $this->assertStringContainsString(e(__('check_ins.summary.not_recorded')), $line);
            $this->assertStringNotContainsString('<a ', $line);
        }
    }

    public function test_an_unsigned_sheet_says_so_counts_its_photos_and_opens(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();
        JobAttachment::factory()->checkIn()->forJob($job)->count(3)->create();
        JobAttachment::factory()->photo()->forJob($job)->count(2)->create();

        $line = $this->summaryFor($job);

        $this->assertStringContainsString(e(__('check_ins.summary.unsigned')), $line);
        $this->assertStringContainsString(e(trans_choice('check_ins.summary.photos', 3)), $line);
        $this->assertStringContainsString(e(__('check_ins.summary.open')), $line);
    }

    public function test_the_photo_count_is_left_out_at_zero(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $line = $this->summaryFor($job);

        $this->assertStringNotContainsString(e(trans_choice('check_ins.summary.photos', 0)), $line);
        $this->assertStringNotContainsString('tabular-nums', $line);
    }

    public function test_a_signed_sheet_names_the_date_and_method_and_is_viewed(): void
    {
        $job = Job::factory()->inProgress()->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $line = $this->summaryFor($job);

        $this->assertStringContainsString(e(__('check_ins.summary.signed', [
            'date' => $checkIn->signed_at->isoFormat('D MMM HH:mm'),
            'method' => __('check_ins.summary.methods.paper'),
        ])), $line);
        $this->assertStringContainsString(e(__('check_ins.summary.view')), $line);
        $this->assertStringNotContainsString(e(__('check_ins.summary.open')), $line);
    }

    public function test_c2_a_completed_jobs_unsigned_sheet_still_opens_because_it_can_be_signed(): void
    {
        $job = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->assertStringContainsString(e(__('check_ins.summary.open')), $this->summaryFor($job));
    }

    public function test_a_cancelled_jobs_unsigned_sheet_is_only_viewed(): void
    {
        $job = Job::factory()->cancelled()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $line = $this->summaryFor($job);

        $this->assertStringContainsString(e(__('check_ins.summary.unsigned')), $line);
        $this->assertStringContainsString(e(__('check_ins.summary.view')), $line);
    }

    /**
     * Serbian has three counted forms. 2 takes the "few" form and 5 the
     * "many" one; a two-form string would print the same word for both.
     */
    public function test_the_photo_count_follows_serbian_plural_forms(): void
    {
        $user = User::factory()->receptionist()->create(['locale' => 'sr']);

        foreach ([2 => 'fotografije', 5 => 'fotografija'] as $count => $word) {
            $job = Job::factory()->inProgress()->create();
            JobCheckIn::factory()->forJob($job)->create();
            JobAttachment::factory()->checkIn()->forJob($job)->count($count)->create();

            $this->assertStringContainsString("{$count} {$word}", $this->summaryFor($job, $user));
        }
    }

    public function test_every_role_sees_the_line(): void
    {
        $job = Job::factory()->inProgress()->create();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->assertStringContainsString(
                e(__('check_ins.summary.record')),
                $this->summaryFor($job, User::factory()->{$role}()->create()),
            );
        }
    }

    private function summaryFor(Job $job, ?User $user = null): string
    {
        $html = (string) $this->actingAs($user ?? User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();

        $label = e(__('check_ins.summary.label'));
        $pattern = '/'.preg_quote($label, '/').'\s*<\/dt>\s*<dd>(.*?)<\/dd>/s';

        $this->assertMatchesRegularExpression($pattern, $html, 'The Check-in fact did not render.');
        preg_match($pattern, $html, $matches);

        return $matches[1];
    }
}
