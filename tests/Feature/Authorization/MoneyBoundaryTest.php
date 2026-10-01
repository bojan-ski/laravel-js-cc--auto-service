<?php

namespace Tests\Feature\Authorization;

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReadsLineItemsTable;
use Tests\TestCase;

/**
 * Rows 9 and 21: prices are hidden from a mechanic at the QUERY level, not in
 * the view.
 *
 * Asserting the query rather than the markup is the difference between testing
 * the money boundary and testing a template. A page that renders no price
 * proves nothing about whether the price was loaded, logged, serialized or
 * dumped. The rendered assertions here are a second layer, never the test.
 *
 * Row 37 — the fourth enforcement shape, a Gate check inside PartImportService
 * against an uploaded header row — is admin-only and lives in AdminOnlyTest
 * beside the other receptionist refusals.
 */
class MoneyBoundaryTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsLineItemsTable;

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function tiers(): array
    {
        return [
            //                            role,     sees unit_price, sees cost_price
            'mechanic' => ['mechanic', false, false],
            'receptionist' => ['receptionist', true, false],
            'admin' => ['admin', true, true],
        ];
    }

    #[DataProvider('tiers')]
    public function test_a_part_query_selects_exactly_the_money_columns_the_tier_may_see(
        string $role,
        bool $seesPrice,
        bool $seesCost,
    ): void {
        Part::factory()->inStock()->create();
        $user = User::factory()->{$role}()->create();

        $part = Part::query()->visibleTo($user)->sole();

        $this->assertSame($seesPrice, array_key_exists('unit_price', $part->getAttributes()));
        $this->assertSame($seesCost, array_key_exists('cost_price', $part->getAttributes()));

        foreach (['unit_price' => $seesPrice, 'cost_price' => $seesCost] as $column => $visible) {
            if ($visible) {
                continue;
            }

            try {
                $part->{$column};
                $this->fail("A {$role} read {$column} without the column being selected.");
            } catch (MissingAttributeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_a_mechanics_job_item_query_omits_the_price_and_front_office_keeps_it(): void
    {
        $item = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->create();

        $mechanic = User::factory()->mechanic()->create();
        $scoped = JobItem::query()->visibleTo($mechanic)->findOrFail($item->getKey());

        $this->assertArrayNotHasKey('unit_price', $scoped->getAttributes());
        $this->expectException(MissingAttributeException::class);
        $scoped->unit_price;
    }

    public function test_a_front_office_job_item_query_does_select_the_price(): void
    {
        $item = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->create();

        foreach (['receptionist', 'admin'] as $role) {
            $scoped = JobItem::query()
                ->visibleTo(User::factory()->{$role}()->create())
                ->findOrFail($item->getKey());

            $this->assertArrayHasKey('unit_price', $scoped->getAttributes());
        }
    }

    public function test_a_mechanics_job_item_query_omits_the_discount_and_front_office_keeps_it(): void
    {
        $item = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->discounted('10.00')->create();

        foreach (['receptionist', 'admin'] as $role) {
            $scoped = JobItem::query()
                ->visibleTo(User::factory()->{$role}()->create())
                ->findOrFail($item->getKey());

            $this->assertSame('10.00', $scoped->discount_percent);
        }

        $mechanic = User::factory()->mechanic()->create();
        $scoped = JobItem::query()->visibleTo($mechanic)->findOrFail($item->getKey());

        // The model declares a '0.00' default for fresh lines; hydration must
        // replace it wholesale, or a mechanic would read a made-up zero.
        $this->assertArrayNotHasKey('discount_percent', $scoped->getAttributes());
        $this->expectException(MissingAttributeException::class);
        $scoped->discount_percent;
    }

    public function test_a_mechanics_job_page_never_names_the_discount_column(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->discounted('10.00')->create();

        $lineReads = $this->queriesTouching($this->queriesFor('mechanic', route('jobs.show', $job)), 'job_items');

        $this->assertNotEmpty($lineReads, 'The mechanic\'s job page read no line items, so nothing here was checked.');

        foreach ($lineReads as $sql) {
            $this->assertStringNotContainsString('discount_percent', $sql, $sql);
            // `select *` names no column while selecting every one, which would
            // make the assertion above vacuous — spec 16's trap.
            $this->assertStringNotContainsString('select *', $sql, $sql);
            $this->assertStringContainsString('`quantity`', $sql, "The line query no longer names its columns: {$sql}");
        }
    }

    public function test_no_discount_total_and_no_discount_panel_reach_a_mechanic(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->discounted('10.00')->create();
        $url = route('jobs.show', ['job' => $job, 'discount' => $item->getKey()]);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get($url)
            ->assertOk()
            ->assertViewHas('discountTotal', fn (mixed $value): bool => $value === null)
            ->assertViewHas('discounting', fn (mixed $value): bool => $value === null)
            ->assertDontSee('id="line-discount-form"', false);

        // The control: the same URL opens the panel and carries the total for
        // front office, so the refusal above is about the role.
        $this->actingAs(User::factory()->receptionist()->create())
            ->get($url)
            ->assertOk()
            ->assertViewHas('discountTotal', fn (?string $total): bool => $total !== null)
            ->assertSee('id="line-discount-form"', false);
    }

    public function test_no_job_total_is_computed_for_a_mechanic(): void
    {
        $job = $this->jobWithLine();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertViewHas('subtotal', fn (mixed $value): bool => $value === null)
            ->assertViewHas('showPricing', false);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertViewHas('showPricing', true);
    }

    /**
     * The invoice area is unreachable for a mechanic by omission, not by hidden
     * markup: the job page's query never loads the relation at all.
     */
    public function test_the_job_pages_query_does_not_load_the_invoice_relation_for_a_mechanic(): void
    {
        $job = $this->lockedJobWithInvoice();

        $mechanicQueries = $this->queriesFor('mechanic', route('jobs.show', $job));

        $this->assertSame(
            [],
            $this->queriesTouching($mechanicQueries, 'invoices'),
            'A mechanic\'s job page read the invoices table.',
        );

        // The control: the same page for front office DOES read it, so the
        // assertion above is about the role and not about the page.
        $this->assertNotSame(
            [],
            $this->queriesTouching($this->queriesFor('receptionist', route('jobs.show', $job)), 'invoices'),
            'Front office no longer loads the invoice, so the mechanic assertion proves nothing.',
        );
    }

    public function test_a_mechanics_job_page_carries_no_price_column_and_no_invoice_number(): void
    {
        $job = $this->lockedJobWithInvoice();
        $invoice = $job->activeInvoice()->first();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        // Scoped to the line-items table head: "Price" is a Faker surname and
        // this page renders up to five generated names.
        $this->assertStringNotContainsString(
            __('jobs.items.headers.price'),
            $this->lineItemsTableHead($response),
        );
        $response->assertDontSee($invoice->number);

        $frontOffice = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $this->assertStringContainsString(
            __('jobs.items.headers.price'),
            $this->lineItemsTableHead($frontOffice),
            'Front office lost the price column, so the mechanic assertion proves nothing.',
        );
        $frontOffice->assertSee($invoice->number);
    }

    public function test_a_mechanics_parts_pages_read_no_money_column(): void
    {
        $part = Part::factory()->inStock()->create();

        foreach ([route('parts.index'), route('parts.show', $part)] as $url) {
            $this->assertNoMoneyReachedMemory($this->queriesFor('mechanic', $url), $url);
        }
    }

    /**
     * The rendered second layer for the parts pages. The query assertion above
     * is the real test; this catches a price that reaches the HTML from some
     * other source than the scoped model — a stray lookup inside a partial,
     * say, which would leave the page's own query log clean.
     */
    public function test_a_mechanics_parts_pages_show_neither_price_nor_cost(): void
    {
        $part = Part::factory()->inStock()->create([
            'unit_price' => '4242.42',
            'cost_price' => '2828.28',
        ]);

        foreach ([route('parts.index'), route('parts.show', $part)] as $url) {
            $this->actingAs(User::factory()->mechanic()->create())
                ->get($url)
                ->assertOk()
                ->assertDontSee('4,242.42')
                ->assertDontSee('2,828.28');
        }

        // Controls, per tier: a receptionist sees the price but never the cost,
        // an admin sees both. Without these the assertions above would pass on
        // a page that shows nobody anything.
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('4,242.42')
            ->assertDontSee('2,828.28');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('4,242.42')
            ->assertSee('2,828.28');
    }

    /**
     * The job total is rendered by a component separate from the line-items
     * table head, so a regression could drop the Price column and still print
     * the subtotal. The null-subtotal view assertion above proves the controller
     * did not compute it; this proves the page does not show it.
     */
    public function test_a_mechanics_job_page_shows_no_total(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '2.00', 'unit_price' => '1500.00']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('3,000.00');

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('3,000.00');
    }

    public function test_the_jobs_board_reads_no_monetary_value_for_any_role(): void
    {
        $this->jobWithLine();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->assertNoMoneyReachedMemory(
                $this->queriesFor($role, route('jobs.index')),
                "the jobs board as {$role}",
            );
        }
    }

    public function test_the_dashboard_reads_no_monetary_value_for_any_role(): void
    {
        $this->jobWithLine();
        Part::factory()->lowStock()->create();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->assertNoMoneyReachedMemory(
                $this->queriesFor($role, route('dashboard')),
                "the dashboard as {$role}",
            );
        }
    }

    /**
     * The two accepted service-internal price reads, pinned by behaviour.
     *
     * Adding a part line is the one mechanic-driven request that legitimately
     * reads a price, and it does so twice:
     *
     *  - JobItemService::catalogPrice() reads unit_price as a SCALAR to
     *    snapshot it onto the line;
     *  - StockService::record()'s locked re-read is a `select *` for every role.
     *
     * Neither reaches a view, and both have been reported since spec 16. This
     * asserts their shape rather than asserting them away: cost_price is never
     * read at all, the catalogue read stays a single-column select, and the one
     * whole-row read is the stock lock — identified by `for update`, so a new
     * unlocked `select *` would fail here.
     */
    public function test_the_two_accepted_service_internal_price_reads_keep_their_shape(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = Part::factory()->inStock()->create();
        $mechanic = User::factory()->mechanic()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($mechanic)
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => '2'])
            ->assertRedirect(route('jobs.show', $job));

        $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNotEmpty($queries);

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('cost_price', $sql, "A mechanic's part line read cost_price: {$sql}");
        }

        // Reads only: the line's INSERT names unit_price because writing the
        // snapshot onto the line is the whole point of the read.
        $priceReads = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'unit_price') && str_starts_with($sql, 'select'),
        ));

        $this->assertSame(
            ['select `unit_price` from `parts` where `parts`.`id` = ? limit 1'],
            $priceReads,
            'The catalogue price is no longer read as a single scalar column.',
        );

        $wholeRowReads = array_values(array_filter(
            $queries,
            fn (string $sql): bool => (bool) preg_match('/^select \* from `parts`/', $sql),
        ));

        $this->assertCount(1, $wholeRowReads, 'Exactly one whole-row part read is accepted: the stock lock.');
        $this->assertStringEndsWith('for update', $wholeRowReads[0]);
    }

    public function test_a_mechanics_job_page_reads_nothing_from_the_estimate_tables(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->approved()->forJob($job)->create();

        $queries = $this->queriesFor('mechanic', route('jobs.show', $job));

        $this->assertNotEmpty($queries);
        $this->assertSame([], $this->queriesTouching($queries, 'estimates'));
        $this->assertSame([], $this->queriesTouching($queries, 'estimate_lines'));
    }

    public function test_a_front_office_job_page_does_read_the_estimate_so_the_mechanic_check_can_fail(): void
    {
        $job = $this->jobWithLine();
        Estimate::factory()->forJob($job)->create();

        $this->assertNotEmpty($this->queriesTouching($this->queriesFor('receptionist', route('jobs.show', $job)), 'estimates'));
    }

    public function test_a_mechanics_estimate_requests_are_refused_before_any_estimate_read(): void
    {
        $estimate = Estimate::factory()->create();
        $mechanic = User::factory()->mechanic()->create();
        $requests = [
            ['get', route('estimates.show', $estimate)],
            ['patch', route('estimates.decision.update', $estimate)],
            ['post', route('estimates.email.store', $estimate)],
        ];

        foreach ($requests as [$method, $url]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($mechanic)->{$method}($url, ['decision' => 'approved', 'decision_method' => 'phone'])->assertForbidden();
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            $this->assertSame([], $this->queriesTouching($queries, 'estimates'), "{$method} {$url}");
            $this->assertSame([], $this->queriesTouching($queries, 'estimate_lines'), "{$method} {$url}");
        }
    }

    /**
     * Row 54 at the query level: margin is admin-only because cost_price is,
     * so a receptionist's report must never read the column, not merely leave
     * it out of the table. A whole-row read of `parts` would carry the cost
     * without naming it, which is why that shape is refused too.
     */
    public function test_a_receptionists_report_never_reads_a_cost_price(): void
    {
        $this->invoicedPartLine();

        foreach ([route('reports.index'), route('reports.export')] as $url) {
            $queries = $this->queriesFor('receptionist', $url);

            $this->assertNotEmpty($queries, "{$url} recorded no queries at all, so nothing here was checked.");

            foreach ($queries as $sql) {
                $this->assertStringNotContainsString('cost_price', $sql, "{$url}: {$sql}");
                $this->assertDoesNotMatchRegularExpression('/select \* from `parts`|`parts`\.\*/', $sql, "{$url}: {$sql}");
            }
        }

        $this->assertNotEmpty(
            $this->queriesTouching($this->queriesFor('receptionist', route('reports.index')), 'parts'),
            'The report must read parts at all, or the cost check above could pass on a page that never looked.',
        );
    }

    public function test_an_admins_report_does_read_the_cost_price_so_the_receptionist_check_can_fail(): void
    {
        $this->invoicedPartLine();

        $named = array_filter(
            $this->queriesFor('admin', route('reports.index')),
            fn (string $sql): bool => str_contains($sql, '`cost_price`'),
        );

        $this->assertNotEmpty($named);
    }

    private function invoicedPartLine(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->forPart(Part::factory()->create(['cost_price' => '25.00']))->create(['user_id' => null, 'quantity' => 2]);
        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    private function jobWithLine(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        return $job;
    }

    private function lockedJobWithInvoice(): Job
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        $job->forceFill(['locked_at' => now()->subMinutes(10)])->saveQuietly();

        Invoice::factory()->unpaid()->forJob($job)->create();

        return $job->fresh();
    }

    /**
     * @return list<string>
     */
    private function queriesFor(string $role, string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs(User::factory()->{$role}()->create())->get($url)->assertOk();

        $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());

        DB::disableQueryLog();

        return $queries;
    }

    /**
     * @param  list<string>  $queries
     * @return list<string>
     */
    private function queriesTouching(array $queries, string $table): array
    {
        return array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, "`{$table}`"),
        ));
    }

    /**
     * @param  list<string>  $queries
     */
    private function assertNoMoneyReachedMemory(array $queries, string $context): void
    {
        // Without this the whole assertion is vacuous: an empty log runs the
        // loop zero times and compares [] to [], so a broken capture would
        // report a clean money boundary on a page nobody queried.
        $this->assertNotEmpty($queries, "{$context} recorded no queries at all, so nothing here was checked.");

        foreach ($queries as $sql) {
            foreach (['unit_price', 'cost_price', 'discount_percent', 'discount_total'] as $column) {
                $this->assertStringNotContainsString($column, $sql, "{$context} named {$column}: {$sql}");
            }

            // A named-column assertion alone is vacuous against `select *`,
            // which names nothing while selecting everything — the exact trap
            // spec 16 shipped and had to fix.
            //
            // Anchored at the start of the statement on purpose: an
            // `exists(select * from `parts` …)` subquery selects no column into
            // memory, it answers a boolean, and treating it as a leak would
            // make every filter-tab count a false positive.
            $this->assertDoesNotMatchRegularExpression(
                '/^select \* from `(job_items|parts|invoices)`/',
                $sql,
                "{$context} selected every column of a money-bearing table: {$sql}",
            );
        }

        $this->assertSame([], $this->queriesTouching($queries, 'invoices'), "{$context} read the invoices table.");
    }
}
