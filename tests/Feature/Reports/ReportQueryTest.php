<?php

namespace Tests\Feature\Reports;

use App\Models\Part;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

class ReportQueryTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function pinnedCounts(): array
    {
        return [
            'receptionist' => ['receptionist', 7],
            'admin' => ['admin', 7],
        ];
    }

    /**
     * Measured, then pinned per role: one aggregate each for revenue, VAT and
     * payments, then one line read and one eager load each for labor and
     * parts. actingAs() hands the guard its user, so no user read is logged.
     * The count must not grow with invoices or lines.
     */
    #[DataProvider('pinnedCounts')]
    public function test_the_page_query_count_is_constant_as_invoices_and_lines_grow(string $role, int $pinned): void
    {
        $this->invoices(1);
        $small = $this->pageQueries($role);

        $this->invoices(6);
        $large = $this->pageQueries($role);

        $this->assertCount($pinned, $small, implode("\n", $small));
        $this->assertCount($pinned, $large, implode("\n", $large));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    /**
     * Collected with beforeExecuting(), not the query log, because the log
     * never shows a statement that failed, so an attempted write that errored
     * would be invisible there.
     */
    #[DataProvider('frontOfficeProvider')]
    public function test_a_report_request_only_reads(string $role): void
    {
        $this->invoices(2);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));
        $user = User::factory()->{$role}()->create();

        $attempted = [];
        $listening = true;
        DB::beforeExecuting(function (string $sql) use (&$attempted, &$listening): void {
            if ($listening) {
                $attempted[] = $sql;
            }
        });

        foreach (['reports.index', 'reports.export'] as $route) {
            $response = $this->actingAs($user)->get(route($route, ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();

            if ($route === 'reports.export') {
                $response->streamedContent();
            }
        }

        $listening = false;

        $this->assertNotEmpty($attempted);

        foreach ($attempted as $sql) {
            $this->assertMatchesRegularExpression('/^select\b/i', $sql, "A report request attempted: {$sql}");
        }
    }

    /**
     * A whole-row read of `parts` would carry both prices without naming them,
     * and a whole-row read of `job_items` would do the same for the line
     * prices. Every report read names its columns.
     */
    #[DataProvider('frontOfficeProvider')]
    public function test_no_report_request_reads_a_whole_row_of_parts_or_job_items(string $role): void
    {
        $this->invoices(2);

        $queries = [...$this->queriesFor($role, 'reports.index'), ...$this->queriesFor($role, 'reports.export')];
        $partReads = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `parts`'));
        $lineReads = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `job_items`'));

        $this->assertNotEmpty($partReads);
        $this->assertNotEmpty($lineReads);

        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/select \* from `(parts|job_items)`|`(parts|job_items)`\.\*/', $sql);
        }
    }

    private function invoices(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $job = $this->completedJob();
            $this->laborLine($job, User::factory()->mechanic()->create(), '1.00', '100.00');
            $this->laborLine($job, User::factory()->mechanic()->create(), '2.00', '80.00', '5.00');
            $this->partLine($job, Part::factory()->create(), 2, '40.00');
            $this->partLine($job, Part::factory()->create(), 1, '15.00');
            $this->issueAt($job, '2026-09-1'.$i.' 10:00');
        }
    }

    /**
     * @return list<string>
     */
    private function pageQueries(string $role): array
    {
        return $this->queriesFor($role, 'reports.index');
    }

    /**
     * @return list<string>
     */
    private function queriesFor(string $role, string $route): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));
        $user = User::factory()->{$role}()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->get(route($route, ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();

        if ($route === 'reports.export') {
            $response->streamedContent();
        }

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }
}
