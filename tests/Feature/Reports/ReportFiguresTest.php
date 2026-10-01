<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentMethod;
use App\Models\Part;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

/**
 * Every expected figure here is written out by hand from the fixture's lines,
 * never recomputed by the code under test.
 */
class ReportFiguresTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.vat_rate' => '20']);
    }

    public function test_revenue_sums_the_stored_invoice_columns(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $first = $this->completedJob();
        $this->laborLine($first, $mechanic, '2.00', '100.00', '10.00');
        $this->partLine($first, Part::factory()->create(), 1, '50.00');
        $this->issueAt($first, '2026-09-05 10:00');

        $second = $this->completedJob();
        $this->laborLine($second, $mechanic, '1.00', '100.00');
        $this->issueAt($second, '2026-09-06 10:00');

        $revenue = $this->september()->viewData('revenue');

        $this->assertSame([
            'invoices' => 2,
            'before_discount' => '350.00',
            'discount' => '20.00',
            'subtotal' => '330.00',
            'vat' => '66.00',
            'total' => '396.00',
            'has_discount' => true,
            'voided' => 0,
        ], $revenue);
    }

    public function test_the_discount_rows_show_only_when_a_discount_was_given(): void
    {
        $plain = $this->completedJob();
        $this->laborLine($plain, User::factory()->mechanic()->create(), '1.00', '100.00');
        $this->issueAt($plain, '2026-09-05 10:00');

        $this->september()
            ->assertDontSee(__('reports.revenue.before_discount'))
            ->assertDontSee(__('reports.revenue.discount'));

        $discounted = $this->completedJob();
        $this->laborLine($discounted, User::factory()->mechanic()->create(), '1.00', '100.00', '25.00');
        $this->issueAt($discounted, '2026-09-06 10:00');

        $this->september()
            ->assertSeeInOrder([__('reports.revenue.before_discount'), '200.00', __('reports.revenue.discount'), '25.00']);
    }

    public function test_two_vat_rates_in_one_period_give_two_rows_in_ascending_order(): void
    {
        $this->invoiceAt('2026-09-05 10:00', '1.00', '100.00');

        config(['shop.vat_rate' => '10']);
        $this->invoiceAt('2026-09-06 10:00', '2.00', '100.00');

        $this->assertSame([
            ['rate' => '10', 'base' => '200.00', 'vat' => '20.00'],
            ['rate' => '20', 'base' => '100.00', 'vat' => '20.00'],
        ], $this->september()->viewData('vat'));
    }

    public function test_payments_lists_each_method_then_not_recorded_then_unpaid(): void
    {
        $this->payAt($this->invoiceAt('2026-09-02 10:00', '1.00', '100.00'), '2026-09-02 11:00', PaymentMethod::Cash);
        $this->payAt($this->invoiceAt('2026-09-03 10:00', '1.00', '200.00'), '2026-09-03 11:00', PaymentMethod::Card);
        $this->payAt($this->invoiceAt('2026-09-04 10:00', '1.00', '300.00'), '2026-09-04 11:00', PaymentMethod::Cash);
        $this->payWithoutMethod($this->invoiceAt('2026-09-05 10:00', '1.00', '400.00'));
        $this->invoiceAt('2026-09-06 10:00', '1.00', '500.00');

        $response = $this->september();

        $this->assertSame([
            ['key' => 'cash', 'label' => PaymentMethod::Cash->label(), 'invoices' => 2, 'amount' => '480.00'],
            ['key' => 'card', 'label' => PaymentMethod::Card->label(), 'invoices' => 1, 'amount' => '240.00'],
            ['key' => 'unrecorded', 'label' => __('reports.payments.unrecorded'), 'invoices' => 1, 'amount' => '480.00'],
            ['key' => 'unpaid', 'label' => __('reports.payments.unpaid'), 'invoices' => 1, 'amount' => '600.00'],
        ], $response->viewData('payments'));

        $response->assertDontSee(PaymentMethod::BankTransfer->label());
    }

    public function test_an_invoice_paid_after_the_period_counts_as_paid_in_it(): void
    {
        $this->payAt($this->invoiceAt('2026-09-29 10:00'), '2026-10-02 10:00', PaymentMethod::Card);

        $this->assertSame(['card'], array_column($this->september()->viewData('payments'), 'key'));
    }

    public function test_labor_is_summed_per_mechanic_and_ordered_by_hours_then_name(): void
    {
        $zed = User::factory()->mechanic()->create(['name' => 'Zed Mechanic']);
        $ann = User::factory()->mechanic()->create(['name' => 'Ann Mechanic']);
        $gone = User::factory()->mechanic()->create(['name' => 'Gone Mechanic']);

        $first = $this->completedJob();
        $this->laborLine($first, $zed, '2.00', '100.00', '10.00');
        $this->laborLine($first, $ann, '3.00', '80.00');
        $this->issueAt($first, '2026-09-05 10:00');

        $second = $this->completedJob();
        $this->laborLine($second, $zed, '1.00', '100.00');
        $this->laborLine($second, $gone, '4.00', '50.00');
        $this->issueAt($second, '2026-09-06 10:00');

        $gone->delete();

        $response = $this->september();

        $this->assertSame(
            [['Gone Mechanic', '4.00', '200.00'], ['Ann Mechanic', '3.00', '240.00'], ['Zed Mechanic', '3.00', '280.00']],
            array_map(fn (array $row): array => [$row['user']->name, $row['hours'], $row['amount']], $response->viewData('labor')),
        );

        $response->assertSeeInOrder(['Gone Mechanic', '('.__('app.deleted').')', '4', '200.00', 'Ann Mechanic', '3', '240.00']);
    }

    public function test_parts_use_the_discounted_line_total_and_keep_a_trashed_part(): void
    {
        $pads = Part::factory()->create(['sku' => 'BP-1', 'name' => 'Brake pads']);
        $filter = Part::factory()->create(['sku' => 'OF-1', 'name' => 'Oil filter']);
        $wiper = Part::factory()->create(['sku' => 'WB-1', 'name' => 'Wiper blade']);

        $job = $this->completedJob();
        $this->partLine($job, $pads, 2, '50.00', '10.00');
        $this->partLine($job, $filter, 1, '90.00');
        $this->partLine($job, $wiper, 3, '10.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $other = $this->completedJob();
        $this->partLine($other, $wiper, 1, '10.00');
        $this->issueAt($other, '2026-09-06 10:00');

        $wiper->delete();

        $response = $this->september();
        $parts = $response->viewData('parts');

        $this->assertSame(
            [['BP-1', 2, '90.00'], ['OF-1', 1, '90.00'], ['WB-1', 4, '40.00']],
            array_map(fn (array $row): array => [$row['part']->sku, $row['quantity'], $row['revenue']], $parts['rows']),
        );
        $this->assertSame(7, $parts['quantity']);
        $this->assertSame('220.00', $parts['revenue']);

        $response->assertSeeInOrder(['WB-1', 'Wiper blade', '('.__('app.deleted').')']);
    }

    /**
     * Inserted out of order on purpose: groupBy keeps insertion order and the
     * sort is stable, so a fixture already in the expected order would pass
     * with the sort deleted.
     */
    public function test_parts_are_ordered_by_revenue_then_sku(): void
    {
        $job = $this->completedJob();
        $this->partLine($job, Part::factory()->create(['sku' => 'ZZ-9']), 1, '40.00');
        $this->partLine($job, Part::factory()->create(['sku' => 'MM-5']), 1, '90.00');
        $this->partLine($job, Part::factory()->create(['sku' => 'AA-1']), 1, '90.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $response = $this->september();

        $this->assertSame(['AA-1', 'MM-5', 'ZZ-9'], array_map(fn (array $row): string => $row['part']->sku, $response->viewData('parts')['rows']));
        $response->assertSeeInOrder(['AA-1', 'MM-5', 'ZZ-9']);
    }

    /**
     * Rendered, not only view data: a view that swapped two keys would keep
     * every viewData assertion green.
     */
    public function test_the_revenue_vat_and_payment_figures_reach_the_page(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $first = $this->completedJob();
        $this->laborLine($first, $mechanic, '2.00', '100.00', '10.00');
        $this->partLine($first, Part::factory()->create(), 1, '50.00');
        $this->payAt($this->issueAt($first, '2026-09-05 10:00'), '2026-09-05 11:00', PaymentMethod::Cash);

        config(['shop.vat_rate' => '10']);
        $second = $this->completedJob();
        $this->laborLine($second, $mechanic, '1.00', '100.00');
        $this->issueAt($second, '2026-09-06 10:00');

        $this->september()->assertSeeInOrder([
            __('reports.revenue.invoices'), '2',
            __('reports.revenue.subtotal'), '330.00',
            __('reports.revenue.vat'), '56.00',
            __('reports.revenue.total'), '386.00',
            __('reports.vat.rate_value', ['rate' => '10']), '100.00', '10.00',
            __('reports.vat.rate_value', ['rate' => '20']), '230.00', '46.00',
            PaymentMethod::Cash->label(), '1', '276.00',
            __('reports.payments.unpaid'), '1', '110.00',
        ]);
    }

    public function test_bank_transfer_takes_its_enum_place_before_not_recorded_and_unpaid(): void
    {
        $this->invoiceAt('2026-09-02 10:00');
        $this->payWithoutMethod($this->invoiceAt('2026-09-03 10:00'));
        $this->payAt($this->invoiceAt('2026-09-04 10:00'), '2026-09-04 11:00', PaymentMethod::BankTransfer);
        $this->payAt($this->invoiceAt('2026-09-05 10:00'), '2026-09-05 11:00', PaymentMethod::Card);
        $this->payAt($this->invoiceAt('2026-09-06 10:00'), '2026-09-06 11:00', PaymentMethod::Cash);

        $this->assertSame(
            ['cash', 'card', 'bank_transfer', 'unrecorded', 'unpaid'],
            array_column($this->september()->viewData('payments'), 'key'),
        );
    }

    /**
     * Two status/method groups can land in one bucket only if an unpaid
     * invoice carries a method. Nothing in the application writes that, but
     * the row is merged rather than overwritten if it ever exists.
     */
    public function test_two_groups_that_map_to_one_payment_row_are_merged(): void
    {
        $this->invoiceAt('2026-09-02 10:00', '1.00', '100.00');
        $stray = $this->invoiceAt('2026-09-03 10:00', '1.00', '200.00');
        $stray->payment_method = PaymentMethod::Card;
        $stray->save();

        $this->assertSame(
            [['key' => 'unpaid', 'label' => __('reports.payments.unpaid'), 'invoices' => 2, 'amount' => '360.00']],
            $this->september()->viewData('payments'),
        );
    }

    public function test_a_void_counts_in_the_period_it_was_issued_not_the_one_it_was_voided_in(): void
    {
        $this->invoiceAt('2026-08-10 10:00');
        $this->invoiceAt('2026-09-10 10:00');
        $this->voidAt($this->invoiceAt('2026-08-25 10:00'), '2026-09-02 10:00');

        $this->assertSame(1, $this->report('2026-08-01', '2026-08-31')->viewData('revenue')['voided']);
        $this->assertSame(0, $this->september()->viewData('revenue')['voided']);
    }

    public function test_void_invoices_count_nowhere_and_are_named_in_the_voided_line(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $this->invoiceAt('2026-09-02 10:00', '1.00', '100.00');

        $paidThenVoided = $this->completedJob();
        $this->laborLine($paidThenVoided, $mechanic, '5.00', '100.00');
        $this->partLine($paidThenVoided, Part::factory()->create(), 1, '70.00');
        $invoice = $this->issueAt($paidThenVoided, '2026-09-03 10:00');
        $this->payAt($invoice, '2026-09-03 11:00', PaymentMethod::Cash);
        $this->voidAt($invoice->fresh(), '2026-09-04 10:00');

        $this->voidAt($this->invoiceAt('2026-09-05 10:00', '1.00', '900.00'), '2026-09-05 11:00');

        $response = $this->september();
        $revenue = $response->viewData('revenue');

        $this->assertSame(1, $revenue['invoices']);
        $this->assertSame('120.00', $revenue['total']);
        $this->assertSame(2, $revenue['voided']);
        $this->assertSame([['rate' => '20', 'base' => '100.00', 'vat' => '20.00']], $response->viewData('vat'));
        $this->assertSame(['unpaid'], array_column($response->viewData('payments'), 'key'));
        $this->assertNotContains($mechanic->getKey(), array_map(fn (array $row): int => $row['user']->getKey(), $response->viewData('labor')));
        $this->assertSame([], $response->viewData('parts')['rows']);

        $response->assertSee(trans_choice('reports.voided', 2, ['count' => 2]));
    }

    public function test_lines_follow_the_non_void_invoice_into_its_own_period(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $septemberThenOctober = $this->completedJob();
        $this->laborLine($septemberThenOctober, $mechanic, '3.00', '100.00');
        $this->voidAt($this->issueAt($septemberThenOctober, '2026-09-10 10:00'), '2026-09-20 10:00');
        $this->issueAt($septemberThenOctober, '2026-10-05 10:00');

        $augustThenSeptember = $this->completedJob();
        $this->laborLine($augustThenSeptember, $mechanic, '2.00', '100.00');
        $this->voidAt($this->issueAt($augustThenSeptember, '2026-08-25 10:00'), '2026-08-28 10:00');
        $this->issueAt($augustThenSeptember, '2026-09-05 10:00');

        $september = $this->september()->viewData('labor');
        $august = $this->report('2026-08-01', '2026-08-31');
        $october = $this->report('2026-10-01', '2026-10-31')->viewData('labor');

        $this->assertSame([['2.00', '200.00']], array_map(fn (array $row): array => [$row['hours'], $row['amount']], $september));
        $this->assertSame([['3.00', '300.00']], array_map(fn (array $row): array => [$row['hours'], $row['amount']], $october));
        $this->assertSame([], $august->viewData('labor'));
        $this->assertSame(1, $august->viewData('revenue')['voided']);
    }

    /**
     * Without an explicit single column below xl the grid's implicit track is
     * `auto`, sized to the widest table's min-content, so the admin's six
     * column parts table pushed every card to 580px on a 375px phone and the
     * whole page scrolled sideways. Measured in a browser during review; a
     * request test can only pin the class that prevents it.
     */
    public function test_the_section_grid_has_a_single_shrinkable_column_below_xl(): void
    {
        $this->invoiceAt('2026-09-05 10:00');

        $this->assertMatchesRegularExpression(
            '/<div class="[^"]*\bgrid\b[^"]*\bgrid-cols-1\b[^"]*\bxl:grid-cols-2\b[^"]*">\s*<section/',
            $this->september()->getContent(),
        );
    }

    public function test_a_period_with_no_invoices_shows_only_the_empty_state(): void
    {
        $this->september()
            ->assertSee(__('reports.empty.title'))
            ->assertSee(__('reports.empty.body'))
            ->assertDontSee(__('reports.revenue.title'))
            ->assertDontSee(__('reports.parts.title'))
            ->assertDontSee(trans_choice('reports.voided', 1, ['count' => 1]));
    }

    public function test_a_period_with_only_void_invoices_explains_why_nothing_counts(): void
    {
        $this->voidAt($this->invoiceAt('2026-09-05 10:00'), '2026-09-06 10:00');

        $this->september()
            ->assertSee(__('reports.empty.title'))
            ->assertSee(trans_choice('reports.voided', 1, ['count' => 1]))
            ->assertDontSee(__('reports.revenue.title'));
    }

    public function test_a_period_without_labor_says_so_in_its_section(): void
    {
        $job = $this->completedJob();
        $this->partLine($job, Part::factory()->create(), 1, '50.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $this->september()
            ->assertSee(__('reports.labor.empty'))
            ->assertDontSee(__('reports.parts.empty'));
    }

    public function test_a_period_without_parts_says_so_in_its_section(): void
    {
        $this->invoiceAt('2026-09-05 10:00');

        $this->september()
            ->assertSee(__('reports.parts.empty'))
            ->assertDontSee(__('reports.labor.empty'));
    }

    private function september(): TestResponse
    {
        return $this->report('2026-09-01', '2026-09-30');
    }

    private function report(string $from, string $to): TestResponse
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00'));

        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.index', ['from' => $from, 'to' => $to]))
            ->assertOk();
    }
}
