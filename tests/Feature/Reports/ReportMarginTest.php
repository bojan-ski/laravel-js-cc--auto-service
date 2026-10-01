<?php

namespace Tests\Feature\Reports;

use App\Models\Part;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

/**
 * Row 54. Which role may SEE margin is rendering; whether the receptionist's
 * request ever READ a cost is the query-level half, asserted in
 * MoneyBoundaryTest beside the other price boundaries.
 */
class ReportMarginTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    private Part $pads;

    private Part $loss;

    private Part $uncosted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pads = Part::factory()->create(['sku' => 'BP-1', 'cost_price' => '30.00']);
        $this->loss = Part::factory()->create(['sku' => 'CL-1', 'cost_price' => '80.00']);
        $this->uncosted = Part::factory()->create(['sku' => 'OF-1', 'cost_price' => null]);

        $job = $this->completedJob();
        $this->partLine($job, $this->pads, 2, '50.00');
        $this->partLine($job, $this->loss, 1, '50.00');
        $this->partLine($job, $this->uncosted, 1, '40.00');
        $this->issueAt($job, '2026-09-05 10:00');
    }

    public function test_an_admin_sees_cost_and_margin_at_the_current_cost_price(): void
    {
        $this->pads->cost_price = '35.00';
        $this->pads->save();

        $response = $this->reportAs('admin');
        $parts = $response->viewData('parts');

        $this->assertSame(
            [['BP-1', '70.00', '30.00'], ['CL-1', '80.00', '-30.00'], ['OF-1', null, null]],
            array_map(fn (array $row): array => [$row['part']->sku, $row['cost'], $row['margin']], $parts['rows']),
        );

        $response->assertSee(__('reports.parts.cost'))
            ->assertSee(__('reports.parts.margin'))
            ->assertSeeInOrder(['CL-1', '50.00', '80.00', '-30.00']);
    }

    public function test_a_part_without_a_cost_is_left_out_of_the_totals_and_counted(): void
    {
        $response = $this->reportAs('admin');
        $parts = $response->viewData('parts');

        $this->assertSame('190.00', $parts['revenue']);
        $this->assertSame('140.00', $parts['cost']);
        $this->assertSame('10.00', $parts['margin']);
        $this->assertSame(1, $parts['without_cost']);

        $response->assertSee(trans_choice('reports.parts.without_cost', 1, ['count' => 1]));
        $response->assertSeeInOrder(['OF-1', '40.00', __('app.value_none_spoken'), __('app.value_none_spoken')], false);
    }

    public function test_a_part_costing_zero_is_costed_not_left_out(): void
    {
        $free = Part::factory()->create(['sku' => 'FR-1', 'cost_price' => '0.00']);
        $job = $this->completedJob();
        $this->partLine($job, $free, 2, '10.00');
        $this->issueAt($job, '2026-09-06 10:00');

        $parts = $this->reportAs('admin')->viewData('parts');
        $row = collect($parts['rows'])->first(fn (array $row): bool => $row['part']->sku === 'FR-1');

        $this->assertSame('0.00', $row['cost']);
        $this->assertSame('20.00', $row['margin']);
        $this->assertSame(1, $parts['without_cost']);
        $this->assertSame('140.00', $parts['cost']);
        $this->assertSame('30.00', $parts['margin']);
    }

    public function test_a_receptionist_sees_neither_cost_nor_margin(): void
    {
        $response = $this->reportAs('receptionist');
        $parts = $response->viewData('parts');

        $this->assertNull($parts['cost']);
        $this->assertNull($parts['margin']);
        $this->assertSame(0, $parts['without_cost']);
        $this->assertSame([null], array_unique(array_column($parts['rows'], 'cost')));

        $response->assertDontSee(__('reports.parts.cost'))
            ->assertDontSee(__('reports.parts.margin'))
            ->assertDontSee('-30.00')
            ->assertDontSee(trans_choice('reports.parts.without_cost', 1, ['count' => 1]));
    }

    private function reportAs(string $role): TestResponse
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));

        return $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('reports.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk();
    }
}
