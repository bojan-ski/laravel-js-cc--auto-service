<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

class ReportPeriodTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    /**
     * Both edges are inclusive calendar days in Europe/Belgrade. A comparison
     * in UTC, or `issued_at <= to` at midnight, moves one of these four.
     */
    public function test_an_invoice_counts_from_midnight_on_from_until_the_last_second_of_to(): void
    {
        $this->assertSame('Europe/Belgrade', config('app.timezone'));

        $this->invoiceAt('2026-08-31 23:59:59');
        $first = $this->invoiceAt('2026-09-01 00:00:00');
        $last = $this->invoiceAt('2026-09-30 23:59:59');
        $this->invoiceAt('2026-10-01 00:00:00');

        $this->travelTo(CarbonImmutable::parse('2026-10-05 12:00'));

        $response = $this->report(['from' => '2026-09-01', 'to' => '2026-09-30']);

        $this->assertSame(2, $response->viewData('revenue')['invoices']);
        $this->assertSame(bcadd($first->total, $last->total, 2), $response->viewData('revenue')['total']);
    }

    public function test_a_single_day_period_counts_that_whole_day(): void
    {
        $this->invoiceAt('2026-09-14 23:59:59');
        $this->invoiceAt('2026-09-15 00:00:00');
        $this->invoiceAt('2026-09-15 23:59:59');
        $this->invoiceAt('2026-09-16 00:00:00');

        $response = $this->report(['from' => '2026-09-15', 'to' => '2026-09-15']);

        $this->assertSame(2, $response->viewData('revenue')['invoices']);
    }

    public function test_the_default_period_is_this_month_to_date(): void
    {
        $this->invoiceAt('2026-08-31 23:59:59');
        $this->invoiceAt('2026-09-01 00:00:00');
        $this->invoiceAt('2026-09-15 11:00:00');

        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));

        $response = $this->report();

        $this->assertTrue(CarbonImmutable::parse('2026-09-01')->equalTo($response->viewData('from')));
        $this->assertTrue(CarbonImmutable::parse('2026-09-15')->equalTo($response->viewData('to')));
        $this->assertSame(2, $response->viewData('revenue')['invoices']);
        $response->assertSee(__('reports.period.summary', ['from' => '1 Sep 2026', 'to' => '15 Sep 2026']));
    }

    public function test_on_the_first_of_the_month_the_default_period_is_that_day_alone(): void
    {
        $this->invoiceAt('2026-08-31 23:59:59');
        $this->invoiceAt('2026-09-01 07:00:00');

        $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00'));

        $response = $this->report();

        $this->assertTrue(CarbonImmutable::parse('2026-09-01')->equalTo($response->viewData('from')));
        $this->assertTrue(CarbonImmutable::parse('2026-09-01')->equalTo($response->viewData('to')));
        $this->assertSame(1, $response->viewData('revenue')['invoices']);
    }

    public function test_a_future_period_is_valid_and_finds_nothing(): void
    {
        $this->invoiceAt('2026-09-10 10:00');
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));

        $this->report(['from' => '2027-01-01', 'to' => '2027-01-31'])
            ->assertSessionHasNoErrors()
            ->assertSee(__('reports.empty.title'));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: array<string, string>}>
     */
    public static function invalidPeriodProvider(): array
    {
        return [
            'from alone' => [['from' => '2026-08-03'], 'to', ['from' => '2026-08-03']],
            'to alone' => [['to' => '2026-08-03'], 'from', ['to' => '2026-08-03']],
            'to before from' => [['from' => '2026-08-20', 'to' => '2026-08-03'], 'to', ['from' => '2026-08-20', 'to' => '2026-08-03']],
            'a malformed date' => [['from' => '03.08.2026', 'to' => '2026-08-20'], 'from', ['from' => '03.08.2026', 'to' => '2026-08-20']],
            'an impossible date' => [['from' => '2026-02-30', 'to' => '2026-03-31'], 'from', ['from' => '2026-02-30', 'to' => '2026-03-31']],
            'an unpadded date' => [['from' => '2026-9-1', 'to' => '2026-09-30'], 'from', ['from' => '2026-9-1', 'to' => '2026-09-30']],
        ];
    }

    /**
     * Clearing both date inputs and pressing Apply with JavaScript off sends
     * `?from=&to=`. That is the default period, not a validation error.
     */
    public function test_both_dates_cleared_give_the_default_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('reports.index').'?from=&to=')
            ->assertOk();

        $this->assertTrue(CarbonImmutable::parse('2026-09-01')->equalTo($response->viewData('from')));
        $this->assertTrue(CarbonImmutable::parse('2026-09-15')->equalTo($response->viewData('to')));
    }

    /**
     * The redirect goes to the report itself whatever the referer, because the
     * previous URL of a typed or bookmarked link is `/`, where the errors would
     * never render. Asserted by rendering the target, never through
     * assertSessionHasErrors, which consumes the flashed errors.
     */
    #[DataProvider('invalidPeriodProvider')]
    public function test_an_invalid_period_lands_on_the_report_with_the_error_and_the_values_refilled(array $query, string $field, array $refilled): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
        $user = User::factory()->receptionist()->create();

        foreach (['without a referer' => null, 'with a referer' => route('dashboard')] as $case => $referer) {
            $this->flushSession();
            $request = $referer === null ? $this->actingAs($user) : $this->actingAs($user)->from($referer);

            $request->get(route('reports.index', $query))->assertRedirect(route('reports.index'));

            $html = $this->actingAs($user)->get(route('reports.index'))->assertOk()->getContent();

            $this->assertStringContainsString("id=\"{$field}-error\"", $html, "{$case}: no error rendered under {$field}.");
            $this->assertMatchesRegularExpression("/id=\"{$field}\"[^>]*aria-invalid=\"true\"/", $html, $case);

            foreach ($refilled as $name => $value) {
                $this->assertMatchesRegularExpression(
                    '/id="'.$name.'"[^>]*value="'.preg_quote($value, '/').'"/',
                    $html,
                    "{$case}: {$name} was not refilled with the submitted value.",
                );
            }

            $this->assertStringContainsString(
                e(__('reports.period.summary', ['from' => '1 Oct 2026', 'to' => '15 Oct 2026'])),
                $html,
                "{$case}: the summary must name the default period the figures are for.",
            );
        }
    }

    public function test_the_export_rejects_an_invalid_period_the_same_way(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('reports.export', ['from' => '2026-09-01']))
            ->assertRedirect(route('reports.index'));
    }

    public function test_the_export_link_carries_the_period_shown(): void
    {
        $html = $this->report(['from' => '2026-09-01', 'to' => '2026-09-30'])->getContent();

        $this->assertStringContainsString(
            'href="'.e(route('reports.export', ['from' => '2026-09-01', 'to' => '2026-09-30'])).'"',
            $html,
        );
    }

    /**
     * @param  array<string, string>  $query
     */
    private function report(array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('reports.index', $query))
            ->assertOk();
    }
}
