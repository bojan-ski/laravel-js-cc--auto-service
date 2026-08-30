<?php

namespace Tests\Feature\Jobs;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobLockBannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoicedJob(): Job
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 100]);

        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        return $job->refresh();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frontOfficeRoles(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    public function test_an_unlocked_job_shows_no_banner(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee(__('jobs.show.lock.body'));
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_sees_the_invoice_number_on_the_banner(string $role): void
    {
        $job = $this->invoicedJob();
        $number = Invoice::query()->sole()->number;

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee($number)
            ->assertSee(__('jobs.show.lock.title_with_number', [
                'number' => $number,
                'date' => $job->locked_at->isoFormat('D MMM'),
            ]))
            ->assertSee(__('jobs.show.lock.body'));
    }

    public function test_a_mechanic_sees_the_lock_but_not_the_number(): void
    {
        $job = $this->invoicedJob();
        $number = Invoice::query()->sole()->number;

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.show.lock.title', ['date' => $job->locked_at->isoFormat('D MMM')]))
            ->assertSee(__('jobs.show.lock.body'))
            ->assertDontSee($number);
    }

    /**
     * The money boundary is enforced by the query, not the template: a
     * mechanic's job page must never read the invoices table at all.
     */
    public function test_a_mechanic_page_issues_no_invoice_query(): void
    {
        $job = $this->invoicedJob();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $invoiceQueries = array_filter($queries, fn (string $query): bool => str_contains($query, '`invoices`'));

        $this->assertEmpty($invoiceQueries, "A mechanic must not query invoices:\n".implode("\n", $queries));
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_a_front_office_page_does_query_the_invoice(string $role): void
    {
        $job = $this->invoicedJob();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $invoiceQueries = array_filter($queries, fn (string $query): bool => str_contains($query, '`invoices`'));

        $this->assertNotEmpty($invoiceQueries, 'Front office needs the number, so the relation is read.');
    }

    /**
     * An unlocked job must not load the invoice for the banner — there is none
     * to show. It does issue one `exists` check, because JobPolicy::issue asks
     * canBeInvoiced() whether to render the Issue control; that is the only
     * invoice query a completed unlocked job may make.
     */
    public function test_an_unlocked_job_checks_for_an_invoice_but_never_loads_one(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        // `select exists(select * from `invoices` …)` contains the load's text as
        // a substring, so the existence check has to be excluded explicitly.
        $loads = array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `invoices`')
                && ! str_contains($query, 'exists'),
        );

        $this->assertEmpty($loads, "An unlocked job must not load an invoice:\n".implode("\n", $queries));

        $checks = array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'exists') && str_contains($query, '`invoices`'),
        );

        $this->assertCount(1, $checks, 'Exactly one existence check, from the Issue control gate.');
    }

    /**
     * A working job never reaches canBeInvoiced()'s queries, because the status
     * check short-circuits first. This is what keeps the query counts pinned by
     * JobShowLineItemsViewTest unchanged.
     */
    public function test_a_working_job_issues_no_invoice_query_at_all(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $invoiceQueries = array_filter($queries, fn (string $query): bool => str_contains($query, '`invoices`'));

        $this->assertEmpty($invoiceQueries, "A working job needs no invoice query:\n".implode("\n", $queries));
    }

    /**
     * JobFactory::locked() sets locked_at with no invoice row, and existing
     * tests rely on it. Front office must then get the number-less banner
     * rather than an error or an empty number.
     */
    public function test_a_locked_job_with_no_invoice_renders_the_number_less_banner(): void
    {
        $job = Job::factory()->locked()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.show.lock.title', ['date' => $job->locked_at->isoFormat('D MMM')]))
            ->assertSee(__('jobs.show.lock.body'));
    }
}
