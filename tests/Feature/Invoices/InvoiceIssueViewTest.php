<?php

namespace Tests\Feature\Invoices;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceIssueViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($this->job)->create(['quantity' => 1.50, 'unit_price' => 3000]);

        $part = Part::factory()->create(['unit_price' => 250]);
        JobItem::factory()->forJob($this->job)->forPart($part)->create([
            'quantity' => 2,
            'user_id' => null,
        ]);
    }

    private function confirmation(): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.invoice.create', $this->job))
            ->assertOk();
    }

    public function test_the_page_names_the_job_and_its_vehicle_and_customer(): void
    {
        $this->confirmation()
            ->assertSee($this->job->reference)
            ->assertSee($this->job->vehicle->registration_plate)
            ->assertSee($this->job->vehicle->make)
            ->assertSee($this->job->customer->name);
    }

    public function test_the_totals_are_shown_with_the_vat_rate(): void
    {
        $this->confirmation()
            ->assertSee(__('invoices.issue.subtotal'))
            ->assertSee('5,000.00')
            ->assertSee(__('invoices.issue.vat', ['rate' => '20']))
            ->assertSee('1,000.00')
            ->assertSee(__('invoices.issue.total'))
            ->assertSee('6,000.00');
    }

    public function test_the_totals_are_rendered_with_tabular_figures(): void
    {
        $this->confirmation()->assertSee('tabular-nums', false);
    }

    public function test_the_consequence_warning_is_shown(): void
    {
        $this->confirmation()
            ->assertSee(__('invoices.issue.warning_title'))
            ->assertSee(__('invoices.issue.warning_body'));
    }

    /**
     * A screen reader in forms mode tabs between focusable elements and skips
     * static paragraphs, so a warning that merely precedes the button in DOM
     * order can be missed entirely. Tying it to the submit control with
     * aria-describedby is what makes it reach the person about to lock a job.
     */
    public function test_the_warning_is_announced_with_the_submit_control(): void
    {
        $html = $this->confirmation()->getContent();

        $this->assertStringContainsString('id="issue-warning"', $html);
        $this->assertStringContainsString('aria-describedby="issue-warning"', $html);

        $warningPosition = strpos($html, 'id="issue-warning"');
        $submitPosition = strpos($html, 'aria-describedby="issue-warning"');

        $this->assertLessThan(
            $submitPosition,
            $warningPosition,
            'The warning must precede the control it describes.',
        );
    }

    public function test_the_due_date_is_the_only_input_and_there_is_no_tax_field(): void
    {
        $html = $this->confirmation()->getContent();

        $this->assertStringContainsString('name="due_at"', $html);

        foreach (['name="tax_rate"', 'name="tax_amount"', 'name="total"', 'name="subtotal"', 'name="vat"'] as $field) {
            $this->assertStringNotContainsString($field, $html, 'The rate is stamped by the service, never typed.');
        }
    }

    public function test_the_form_posts_to_the_issue_route(): void
    {
        $this->confirmation()->assertSee('action="'.route('jobs.invoice.store', $this->job).'"', false);
    }

    public function test_cancel_returns_to_the_job(): void
    {
        $this->confirmation()->assertSee('href="'.route('jobs.show', $this->job).'"', false);
    }

    public function test_the_submit_control_names_the_job_for_assistive_technology(): void
    {
        $label = __('invoices.issue.submit_for', ['reference' => $this->job->reference]);

        $this->confirmation()->assertSee('aria-label="'.e($label).'"', false);

        $this->assertStringContainsString(
            __('invoices.issue.submit'),
            $label,
            'WCAG 2.5.3: the visible label must be part of the accessible name.',
        );
    }

    public function test_the_page_has_a_single_top_level_heading(): void
    {
        $html = $this->confirmation()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'Exactly one h1.');
        $this->assertStringNotContainsString('<h3', $html, 'No heading level is skipped.');
    }

    /**
     * Spec 14 shipped a field whose error had nowhere to render. This pins that
     * the due-date error reaches the page, is tied to its input, and that the
     * rejected value comes back.
     */
    public function test_a_rejected_due_date_renders_its_error_and_repopulates(): void
    {
        $past = now()->subDay()->toDateString();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('jobs.invoice.create', $this->job))
            ->followingRedirects()
            ->post(route('jobs.invoice.store', $this->job), ['due_at' => $past])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="due_at-error"', $html);
        $this->assertStringContainsString('aria-describedby="due_at-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('value="'.$past.'"', $html, 'The rejected value is echoed back.');
    }

    public function test_a_job_with_no_part_lines_still_totals_correctly(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.invoice.create', $job))
            ->assertOk()
            ->assertSee('1,000.00')
            ->assertSee('200.00')
            ->assertSee('1,200.00');
    }
}
