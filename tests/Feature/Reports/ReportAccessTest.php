<?php

namespace Tests\Feature\Reports;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function queryProvider(): array
    {
        return [
            'no period' => [[]],
            'a valid period' => [['from' => '2026-09-01', 'to' => '2026-09-30']],
            'an invalid period' => [['from' => 'x']],
            'to before from' => [['from' => '2026-09-30', 'to' => '2026-09-01']],
        ];
    }

    #[DataProvider('queryProvider')]
    public function test_a_mechanic_is_refused_both_routes_whatever_the_query_string(array $query): void
    {
        $this->actingAs(User::factory()->mechanic()->create());

        $this->get(route('reports.index', $query))->assertForbidden();
        $this->get(route('reports.export', $query))->assertForbidden();
    }

    public function test_a_mechanics_refused_request_reads_neither_invoices_nor_job_items(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $this->issuedInvoice();

        foreach (['reports.index', 'reports.export'] as $route) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($mechanic)->get(route($route, ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertForbidden();

            $touched = array_filter(
                array_column(DB::getQueryLog(), 'query'),
                fn (string $sql): bool => str_contains($sql, '`invoices`') || str_contains($sql, '`job_items`'),
            );

            DB::disableQueryLog();

            $this->assertSame([], array_values($touched), "{$route} read money tables before refusing a mechanic.");
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_reaches_the_page_and_the_export(string $role): void
    {
        $this->issuedInvoice();
        $this->actingAs(User::factory()->{$role}()->create());

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee(__('reports.revenue.title'));

        $this->get(route('reports.export'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_a_guest_is_redirected_to_login_from_both_routes(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export'))->assertRedirect(route('login'));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_sees_the_reports_nav_item(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('reports.index').'"', false);
    }

    public function test_a_mechanic_sees_no_reports_nav_item(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('reports.index').'"', false);
    }

    public function test_the_nav_item_is_marked_current_on_the_reports_page(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="'.preg_quote(route('reports.index'), '/').'"[^>]*aria-current="page"/',
            $html,
        );
    }

    private function issuedInvoice(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 100]);
        JobItem::factory()->part()->forJob($job)->create(['part_id' => Part::factory()->create()->getKey(), 'quantity' => 2, 'unit_price' => 50]);
        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }
}
