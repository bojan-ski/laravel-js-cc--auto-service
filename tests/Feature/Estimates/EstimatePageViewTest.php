<?php

namespace Tests\Feature\Estimates;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsRowLinks;
use Tests\TestCase;

class EstimatePageViewTest extends TestCase
{
    use AssertsRowLinks;
    use LazilyRefreshDatabase;

    private function jobFor(?string $email = 'milan@example.com'): Job
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrovic', 'email' => $email, 'address' => 'Futoška 14, Novi Sad']);
        $job = Job::factory()->inProgress()->for($customer)->for(Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123-ZZ']))->create();
        JobItem::factory()->forJob($job)->create(['description' => 'Brake service', 'quantity' => '1.50', 'unit_price' => '4000.00']);

        return $job;
    }

    private function estimate(string $state = 'pending', ?Job $job = null): Estimate
    {
        return Estimate::factory()->withLines()->{$state}()->forJob($job ?? $this->jobFor())->create();
    }

    private function page(Estimate $estimate): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('estimates.show', $estimate))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return list<string>
     */
    private function headingOutline(string $html): array
    {
        preg_match_all('/<(h[1-6])\b[^>]*>(.*?)<\/\1>/s', $html, $headings, PREG_SET_ORDER);

        return array_map(fn (array $match): string => $match[1].':'.html_entity_decode(trim(strip_tags($match[2])), ENT_QUOTES), $headings);
    }

    public function test_a_pending_estimate_offers_the_decision_form_and_email(): void
    {
        $estimate = $this->estimate();
        $html = $this->page($estimate);

        $this->assertStringContainsString('action="'.route('estimates.decision.update', $estimate).'"', $html);
        $this->assertMatchesRegularExpression('/name="_method" value="PATCH"/', $html);
        $this->assertStringContainsString('action="'.route('estimates.email.store', $estimate).'"', $html);
        $this->assertStringContainsString(__('estimates.decision.approve'), $html);
        $this->assertStringContainsString(__('estimates.decision_methods.in_person'), $html);
        $this->assertDoesNotMatchRegularExpression('/type="radio"[^>]*\bchecked\b/', $html, 'Neither choice is preselected.');
        $this->assertStringContainsString('maxlength="500"', $html);
    }

    public function test_an_approved_estimate_shows_its_decision_and_no_decision_form(): void
    {
        $estimate = $this->estimate('approved');
        $html = $this->page($estimate);

        $this->assertStringNotContainsString(route('estimates.decision.update', $estimate), $html);
        $this->assertStringContainsString(route('estimates.email.store', $estimate), $html);
        $this->assertStringContainsString(__('estimates.show.decided_by'), $html);
        $this->assertStringContainsString(__('estimates.decision_methods.phone'), $html);
        $this->assertStringContainsString(__('estimates.statuses.approved'), $html);
    }

    public function test_a_declined_estimate_offers_neither_form(): void
    {
        $estimate = $this->estimate('declined');
        $html = $this->page($estimate);

        $this->assertStringNotContainsString(route('estimates.decision.update', $estimate), $html);
        $this->assertStringNotContainsString(route('estimates.email.store', $estimate), $html);
        $this->assertStringContainsString(__('estimates.statuses.declined'), $html);
    }

    public function test_a_superseded_estimate_shows_its_notice_and_still_its_lines_and_totals(): void
    {
        $estimate = $this->estimate('superseded');
        $html = $this->page($estimate);

        $this->assertStringContainsString(e(__('estimates.show.superseded_notice', ['date' => $estimate->superseded_at->isoFormat('D MMM YYYY')])), $html);
        $this->assertStringContainsString('Brake service', $html);
        $this->assertStringContainsString('7,200.00', $html);
        $this->assertStringNotContainsString(route('estimates.decision.update', $estimate), $html);
        $this->assertStringNotContainsString(route('estimates.email.store', $estimate), $html);
    }

    public function test_the_email_control_is_absent_when_the_customer_has_no_address(): void
    {
        $estimate = $this->estimate(job: $this->jobFor(email: null));

        $this->assertStringNotContainsString(route('estimates.email.store', $estimate), $this->page($estimate));
    }

    public function test_a_soft_deleted_customer_renders_as_marked_text(): void
    {
        $estimate = $this->estimate();
        $estimate->job->customer->delete();

        $html = $this->page($estimate);

        $this->assertStringContainsString('Milan Petrovic', $html);
        $this->assertStringContainsString(__('app.deleted'), $html);
    }

    public function test_the_lines_and_totals_are_the_stored_ones(): void
    {
        $estimate = $this->estimate();
        DB::table('estimates')->where('id', $estimate->getKey())->update(['total' => '7777.77']);

        $html = $this->page($estimate);

        $this->assertStringContainsString('7,777.77', $html);
        $this->assertStringContainsString('6,000.00', $html);
    }

    public function test_the_history_appears_only_when_the_job_has_more_than_one_estimate(): void
    {
        $job = $this->jobFor();
        $only = $this->estimate(job: $job);

        $this->assertStringNotContainsString(__('estimates.show.history'), $this->page($only));

        $newer = $this->estimate('approved', $job);
        $html = $this->page($only);

        $this->assertStringContainsString(__('estimates.show.history'), $html);
        $this->assertRowIsOneLinkTo($html, route('estimates.show', $newer));
        $this->assertRowIsOneLinkTo($html, route('estimates.show', $only));
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('estimates.show', $only), '/').'"\s+aria-current="page"/', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"') - substr_count($this->navOnly($html), 'aria-current="page"'));
    }

    public function test_the_outline_is_one_h1_then_h2_sections(): void
    {
        $job = $this->jobFor();
        $this->estimate('superseded', $job);
        $estimate = $this->estimate(job: $job);

        $this->assertSame([
            'h1:'.$estimate->number,
            'h2:'.__('estimates.show.actions'),
            'h2:'.__('estimates.decision.title'),
            'h2:'.__('estimates.show.details'),
            'h2:'.__('invoices.show.line_items'),
            'h2:'.__('estimates.show.history'),
        ], $this->headingOutline($this->page($estimate)));
    }

    public function test_the_superseded_outline_adds_the_notice_heading_and_drops_the_form(): void
    {
        $estimate = $this->estimate('superseded');

        $this->assertSame([
            'h1:'.$estimate->number,
            'h2:'.__('estimates.show.actions'),
            'h2:'.__('estimates.show.details'),
            'h2:'.__('estimates.statuses.superseded'),
            'h2:'.__('invoices.show.line_items'),
        ], $this->headingOutline($this->page($estimate)));
    }

    public function test_each_control_names_its_estimate_and_contains_its_visible_label(): void
    {
        $estimate = $this->estimate();
        $html = $this->page($estimate);

        $submitFor = __('estimates.decision.submit_for', ['number' => $estimate->number]);
        $emailFor = __('estimates.email.action_for', ['number' => $estimate->number]);

        $this->assertStringContainsString('aria-label="'.e($submitFor).'"', $html);
        $this->assertStringContainsString('aria-label="'.e($emailFor).'"', $html);
        $this->assertStringStartsWith(__('estimates.decision.submit'), $submitFor);
        $this->assertStringStartsWith(__('estimates.email.action'), $emailFor);
    }

    public function test_the_letterhead_prints_and_the_controls_do_not(): void
    {
        $estimate = $this->estimate();
        $html = $this->page($estimate);

        $this->assertMatchesRegularExpression('/<div class="hidden print:block">.*?'.preg_quote(e(__('estimates.identity.prepared_for')), '/').'.*?Futoška 14, Novi Sad.*?BG123-ZZ/s', $html);
        $this->assertStringContainsString(e(__('estimates.identity.heading', ['number' => $estimate->number])), $html);
        $this->assertMatchesRegularExpression('/<h1 class="[^"]*print:hidden[^"]*">/', $html);
        $this->assertMatchesRegularExpression('/<div class="[^"]*print:hidden[^"]*">\s*<h2 class="sr-only">'.preg_quote(e(__('estimates.show.actions')), '/').'/', $html);
        $this->assertMatchesRegularExpression('/<section class="[^"]*print:hidden[^"]*">\s*<h2[^>]*>'.preg_quote(e(__('estimates.decision.title')), '/').'/', $html);
        $this->assertStringContainsString('data-print-slot', $html);
    }

    public function test_a_discounted_estimate_shows_the_amount_before_discount_from_its_stored_columns(): void
    {
        $estimate = Estimate::factory()->forJob($this->jobFor())->create([
            'subtotal' => '7800.00',
            'discount_total' => '200.00',
            'tax_rate' => '20.00',
            'tax_amount' => '1560.00',
            'total' => '9360.00',
        ]);

        $html = $this->page($estimate);

        $this->assertMatchesRegularExpression(
            '/<dt[^>]*>\s*'.preg_quote(e(__('invoices.totals.before_discount')), '/').'\s*<\/dt>\s*<dd><span class="tabular-nums">8,000\.00<\/span>\s*<\/dd>/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<dt[^>]*>\s*'.preg_quote(e(__('invoices.totals.discount')), '/').'\s*<\/dt>\s*<dd><span class="tabular-nums">200\.00<\/span>\s*<\/dd>/',
            $html,
        );
    }

    public function test_the_page_reads_a_pinned_number_of_queries(): void
    {
        $job = $this->jobFor();
        $this->estimate('superseded', $job);
        $estimate = $this->estimate(job: $job);
        $desk = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->actingAs($desk)->get(route('estimates.show', $estimate))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertCount(self::PAGE_QUERIES, $queries, implode("\n", $queries));
    }

    /**
     * Measured, not predicted: the estimate, its job, customer, vehicle, lines
     * and creator (the decider is skipped while decided_by is null), then the
     * history read.
     */
    private const PAGE_QUERIES = 7;

    private function navOnly(string $html): string
    {
        preg_match('/<nav\b.*?<\/nav>/s', $html, $nav);

        return $nav[0] ?? '';
    }
}
