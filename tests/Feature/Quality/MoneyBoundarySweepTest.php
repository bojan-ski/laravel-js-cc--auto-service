<?php

namespace Tests\Feature\Quality;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\QaFixture;
use Tests\Support\WalksPages;
use Tests\TestCase;

/**
 * The money boundary over the whole page inventory in one walk (overview, Key
 * Rules: "Mechanics never see money … hidden at the query level"; spec 14's
 * three-tier part scope; specs 30, 33 and 35 for discount, estimates and
 * report cost).
 *
 * The per-surface tests (MoneyBoundaryTest, JobTemplateMoneyBoundaryTest,
 * CheckInMoneyBoundaryTest, DashboardQueryTest, ReportAccessTest) each name a
 * page, a column and a control proving the column is read for the role that
 * may see it. This sweep asserts only that nothing ELSE leaks anywhere, and
 * because it reads WalksPages::pageInventory(), a page added later is swept
 * without anyone editing this file — RouteSmokeTest already fails a GET route
 * that is missing from the inventory.
 *
 * A streamed response runs its queries only when its body is produced, so the
 * callback reads streamedContent() before the log. The log is flushed at the
 * end of every callback, so a failure names the page that issued the
 * statement and not the one after it.
 *
 * Non-GET requests are out of scope: the two accepted service-internal price
 * reads on a mechanic's writes are pinned by MoneyBoundaryTest and
 * JobTemplateMoneyBoundaryTest.
 */
class MoneyBoundarySweepTest extends TestCase
{
    use LazilyRefreshDatabase;
    use WalksPages;

    private const array MECHANIC_COLUMNS = ['unit_price', 'cost_price', 'discount_percent', 'discount_total', 'line_total', 'subtotal', 'tax_amount', 'tax_rate'];

    private const array MECHANIC_WHOLE_ROW_TABLES = ['job_items', 'parts', 'invoices', 'estimates', 'estimate_lines'];

    private const array MECHANIC_FORBIDDEN_TABLES = ['invoices', 'estimates', 'estimate_lines'];

    /**
     * The detector: every reason $sql breaks the boundary for $role.
     *
     * The whole-row pattern is anchored at the start of the statement, as in
     * MoneyBoundaryTest: an `exists (select * from …)` subquery answers a
     * boolean and selects no column into memory.
     *
     * @return list<string>
     */
    public static function violations(string $role, string $sql): array
    {
        $found = [];

        if ($role === 'mechanic') {
            foreach (self::MECHANIC_COLUMNS as $column) {
                if (preg_match('/\b'.$column.'\b/', $sql) === 1) {
                    $found[] = "names {$column}";
                }
            }

            foreach (self::MECHANIC_WHOLE_ROW_TABLES as $table) {
                if (self::selectsEveryColumnOf($table, $sql)) {
                    $found[] = "selects every column of {$table}";
                }
            }

            foreach (self::MECHANIC_FORBIDDEN_TABLES as $table) {
                if (str_contains($sql, "`{$table}`")) {
                    $found[] = "touches {$table}";
                }
            }
        }

        if ($role === 'receptionist') {
            if (preg_match('/\bcost_price\b/', $sql) === 1) {
                $found[] = 'names cost_price';
            }

            if (self::selectsEveryColumnOf('parts', $sql)) {
                $found[] = 'selects every column of parts';
            }
        }

        return $found;
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function walkingRoles(): array
    {
        return [
            'mechanic' => ['mechanic', 50],
            'receptionist' => ['receptionist', 70],
        ];
    }

    #[DataProvider('walkingRoles')]
    public function test_no_page_reads_money_the_role_may_not_see(string $role, int $floor): void
    {
        $shop = QaFixture::build();
        $statementsByPage = [];
        $leaks = [];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $walked = $this->walk($shop, $this->actorFor($shop, $role), $role, function (string $label, array $entry, TestResponse $response) use ($role, &$statementsByPage, &$leaks): void {
            $this->assertPageRendered($response, $label);

            if ($response->baseResponse instanceof StreamedResponse) {
                $response->streamedContent();
            }

            foreach (DB::getQueryLog() as $query) {
                $statementsByPage[$label][] = $query['query'];

                foreach (self::violations($role, $query['query']) as $reason) {
                    $leaks[] = "{$label} {$reason}: {$query['query']}";
                }
            }

            DB::flushQueryLog();
        });

        DB::disableQueryLog();

        $statements = array_merge(...array_values($statementsByPage));

        $this->assertSame([], $leaks, "The {$role} walk read money it may not see:\n".implode("\n", $leaks));
        $this->assertGreaterThanOrEqual($floor, $walked, "The {$role} walk rendered only {$walked} pages; the inventory has shrunk.");
        $this->assertNotEmpty(self::touching($statements, 'job_items'), "The {$role} walk read no job lines, so the sweep checked pages that read nothing.");
        $this->assertNotEmpty(self::touching($statements, 'parts'), "The {$role} walk read no parts, so the sweep checked pages that read nothing.");

        if ($role === 'receptionist') {
            $this->assertNotEmpty(
                array_filter($statements, fn (string $sql): bool => preg_match('/\bunit_price\b/', $sql) === 1),
                'The receptionist walk named no unit_price, so the mechanic column pattern was never shown to match a real statement.',
            );

            // The export runs its own query only while its body is produced, so
            // this statement exists in the log only if streamedContent() ran
            // before the log was read — the property spec 36's Note 3 is about.
            $this->assertNotEmpty(
                array_filter($statementsByPage['reports.export'] ?? [], fn (string $sql): bool => str_contains($sql, 'from `invoices` inner join `jobs`')),
                'The export\'s own invoice query is missing from its page log, so the streamed body was never read.',
            );

            // A receptionist may read whole line rows, so the receptionist's real
            // statements show the mechanic's whole-row pattern matching
            // something this application actually issues, not only hand-written
            // SQL.
            $wholeRow = array_merge(...array_map(fn (string $sql): array => self::violations('mechanic', $sql), $statements));
            $this->assertContains('selects every column of job_items', $wholeRow, 'No real statement exercised the whole-row pattern.');
        }
    }

    /**
     * The cost pattern must match a statement this application really issues.
     * The `:period` variant is named because partsSold() loads the part only
     * when an invoiced part line falls in the range.
     */
    public function test_an_admins_period_report_does_name_cost_price(): void
    {
        $shop = QaFixture::build();
        $entry = self::pageInventory()['reports.index:period'];

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($shop->admin)->get($this->pageUrl($entry, $shop))->assertOk();

        $statements = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertNotEmpty(
            array_filter($statements, fn (string $sql): bool => preg_match('/\bcost_price\b/', $sql) === 1),
            'The admin period report named no cost_price, so the receptionist cost pattern was never shown to match a real statement.',
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function forbiddenShapes(): array
    {
        return [
            'mechanic names unit_price' => ['mechanic', 'select `id`, `unit_price` from `job_items` where `job_id` = ?', 'names unit_price'],
            'mechanic names cost_price' => ['mechanic', 'select `id`, `cost_price` from `parts`', 'names cost_price'],
            'mechanic names discount_percent' => ['mechanic', 'select `discount_percent` from `job_items`', 'names discount_percent'],
            'mechanic names discount_total' => ['mechanic', 'select sum(`discount_total`) as aggregate from `jobs`', 'names discount_total'],
            'mechanic names line_total' => ['mechanic', 'select `line_total` from `lines`', 'names line_total'],
            'mechanic names subtotal' => ['mechanic', 'select 1 as subtotal', 'names subtotal'],
            'mechanic names tax_amount' => ['mechanic', 'select `tax_amount` from `jobs`', 'names tax_amount'],
            'mechanic names tax_rate' => ['mechanic', 'select `tax_rate` from `jobs`', 'names tax_rate'],
            'mechanic select * job_items' => ['mechanic', 'select * from `job_items` where `job_id` = ?', 'selects every column of job_items'],
            'mechanic select * parts' => ['mechanic', 'select * from `parts` where `parts`.`id` in (1)', 'selects every column of parts'],
            'mechanic parts.*' => ['mechanic', 'select `parts`.* from `parts` inner join `brands` on 1 = 1', 'selects every column of parts'],
            'mechanic job_items.*' => ['mechanic', 'select `job_items`.* from `job_items`', 'selects every column of job_items'],
            'mechanic select * invoices' => ['mechanic', 'select * from `invoices` limit 1', 'touches invoices'],
            'mechanic select * estimates' => ['mechanic', 'select * from `estimates` limit 1', 'touches estimates'],
            'mechanic select * estimate_lines' => ['mechanic', 'select * from `estimate_lines` limit 1', 'touches estimate_lines'],
            'mechanic touches invoices' => ['mechanic', 'select `id` from `jobs` where exists (select 1 from `invoices`)', 'touches invoices'],
            'mechanic touches estimates' => ['mechanic', 'select count(*) as aggregate from `estimates`', 'touches estimates'],
            'mechanic touches estimate_lines' => ['mechanic', 'select `id` from `estimate_lines`', 'touches estimate_lines'],
            'receptionist names cost_price' => ['receptionist', 'select `id`, `cost_price` from `parts`', 'names cost_price'],
            'receptionist select * parts' => ['receptionist', 'select * from `parts` where `parts`.`id` = ? limit 1', 'selects every column of parts'],
            'receptionist parts.*' => ['receptionist', 'select `parts`.* from `parts`', 'selects every column of parts'],
        ];
    }

    #[DataProvider('forbiddenShapes')]
    public function test_the_detector_reports_every_forbidden_shape(string $role, string $sql, string $reason): void
    {
        $this->assertContains($reason, self::violations($role, $sql));
    }

    /**
     * The look-alikes a real walk meets must stay clean, or the sweep fails on
     * statements that load nothing into memory.
     *
     * @return array<string, array{string, string}>
     */
    public static function permittedShapes(): array
    {
        return [
            'mechanic exists subquery over job_items' => ['mechanic', 'select count(*) as aggregate from `jobs` where exists (select * from `job_items` where `jobs`.`id` = `job_items`.`job_id`)'],
            'mechanic named part columns' => ['mechanic', 'select `id`, `sku`, `name`, `quantity_in_stock` from `parts`'],
            'mechanic jobs.*' => ['mechanic', 'select `jobs`.* from `jobs` inner join `vehicles` on 1 = 1'],
            'receptionist unit_price' => ['receptionist', 'select `id`, `unit_price` from `parts`'],
            'receptionist select * job_items' => ['receptionist', 'select * from `job_items` where `job_id` = ?'],
            'receptionist invoices' => ['receptionist', 'select * from `invoices` limit 1'],
        ];
    }

    #[DataProvider('permittedShapes')]
    public function test_the_detector_leaves_permitted_statements_alone(string $role, string $sql): void
    {
        $this->assertSame([], self::violations($role, $sql));
    }

    private static function selectsEveryColumnOf(string $table, string $sql): bool
    {
        return preg_match('/^select \* from `'.$table.'`/', $sql) === 1
            || str_contains($sql, "`{$table}`.*");
    }

    /**
     * @param  list<string>  $statements
     * @return list<string>
     */
    private static function touching(array $statements, string $table): array
    {
        return array_values(array_filter($statements, fn (string $sql): bool => str_contains($sql, "`{$table}`")));
    }
}
