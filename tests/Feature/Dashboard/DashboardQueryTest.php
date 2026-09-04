<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The properties this file covers are invisible to rendered output: which columns
 * reach memory, and whether the query count grows with the row count. Nothing on
 * the page displays a price, so a page-content assertion would pass with the
 * money boundary removed entirely.
 */
class DashboardQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fixture(int $each): void
    {
        for ($i = 1; $i <= $each; $i++) {
            $customer = Customer::factory()->create();
            $vehicle = Vehicle::factory()->for($customer)->create();

            Appointment::factory()->for($customer)->for($vehicle)->create([
                'scheduled_at' => CarbonImmutable::today()->setTime(8, 0)->addMinutes($i),
            ]);

            Job::factory()->for($customer)->for($vehicle)->received()->create();
            Part::factory()->create(['quantity_in_stock' => 1, 'reorder_level' => 4]);
        }
    }

    /**
     * @return list<string>
     */
    private function queriesFor(string $role): array
    {
        $actor = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($actor)->get(route('dashboard'))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    /**
     * Both halves matter. The negative alone is the trap spec 16 fell into: a
     * `select *` names no columns, so "does not contain unit_price" is vacuously
     * true exactly when the boundary is broken. The positive control fails the
     * moment the explicit select is dropped.
     */
    #[DataProvider('roleProvider')]
    public function test_no_price_column_is_ever_selected_from_parts(string $role): void
    {
        $this->fixture(2);

        $queries = $this->queriesFor($role);
        $partQueries = array_values(array_filter($queries, fn (string $q): bool => str_contains($q, '`parts`')));

        $this->assertNotEmpty($partQueries, 'The dashboard issued no parts query at all.');

        $this->assertContains(
            'select `id`, `name`, `quantity_in_stock`, `reorder_level` from `parts` where `quantity_in_stock` <= `reorder_level` and `parts`.`deleted_at` is null order by `name` asc, `id` asc limit 12',
            $partQueries,
            'The low-stock panel no longer selects an explicit column list, so it hydrates every price column.',
        );

        foreach ($partQueries as $query) {
            $this->assertStringNotContainsString('unit_price', $query, "A parts query reaches for a price: {$query}");
            $this->assertStringNotContainsString('cost_price', $query, "A parts query reaches for a price: {$query}");
        }
    }

    /**
     * The structural half of the money boundary. The page's freedom from a
     * visibility scope rests on it reading no money-bearing table at all, so a
     * revenue or outstanding-invoice panel cannot be added here without this
     * failing first — which is the point the spec's Notes argue at length.
     */
    #[DataProvider('roleProvider')]
    public function test_no_money_bearing_table_is_queried_at_all(string $role): void
    {
        $this->fixture(2);

        foreach ($this->queriesFor($role) as $query) {
            $this->assertStringNotContainsString('`invoices`', $query, "The dashboard read the invoices table: {$query}");
            $this->assertStringNotContainsString('`job_items`', $query, "The dashboard read the line-items table: {$query}");
        }
    }

    /**
     * Every panel orders by a primary column then `id`, so that ties resolve the
     * same way on every request — which matters most for the two capped panels,
     * where a tie decides which rows are shown at all.
     *
     * This is asserted against the SQL rather than the rendered order because
     * rendered order CANNOT see it: dropping all three tiebreaks was measured to
     * leave every rendering test green, since MySQL returns primary-key order here
     * anyway. The three `..._are_ordered_by_id` rendering tests document the
     * intent; this one is what actually fails when a tiebreak is removed.
     */
    public function test_every_panel_query_carries_a_deterministic_tiebreak(): void
    {
        $this->fixture(2);

        $queries = $this->queriesFor('receptionist');

        $ordered = [
            '`appointments`' => 'order by `scheduled_at` asc, `id` asc',
            '`jobs`' => 'order by `created_at` asc, `id` asc',
            '`parts`' => 'order by `name` asc, `id` asc',
        ];

        foreach ($ordered as $table => $clause) {
            $matching = array_filter(
                $queries,
                fn (string $q): bool => str_contains($q, $table) && str_contains($q, 'order by'),
            );

            $this->assertNotEmpty($matching, "No ordered query was issued against {$table}.");

            foreach ($matching as $query) {
                $this->assertStringContainsString($clause, $query, "The {$table} panel query has no deterministic tiebreak: {$query}");
            }
        }
    }

    public function test_the_query_count_does_not_grow_with_the_row_count(): void
    {
        $this->fixture(1);
        $small = count($this->queriesFor('receptionist'));

        $this->fixture(60);
        $large = count($this->queriesFor('receptionist'));

        $this->assertSame($small, $large, 'The dashboard issues a query per row.');
    }

    public function test_panel_counts_are_obtained_by_counting_not_by_loading(): void
    {
        $this->fixture(3);

        $queries = $this->queriesFor('receptionist');
        $counts = array_values(array_filter($queries, fn (string $q): bool => str_contains($q, 'count(*)')));

        $this->assertCount(2, $counts, 'Expected exactly one count query for jobs and one for parts: '.implode("\n", $queries));
        $this->assertNotEmpty(array_filter($counts, fn (string $q): bool => str_contains($q, '`jobs`')));
        $this->assertNotEmpty(array_filter($counts, fn (string $q): bool => str_contains($q, '`parts`')));
    }

    /**
     * The active-jobs panel caps its rows, so a count taken from the loaded
     * collection would report 10 forever. This pins the two as different numbers.
     */
    public function test_the_job_count_is_not_derived_from_the_capped_collection(): void
    {
        $this->fixture(14);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('jobs', fn ($jobs): bool => $jobs->count() === 10)
            ->assertViewHas('jobCount', 14);
    }

    /**
     * The dashboard panels are lists, not tables, so they sit outside
     * TableHeaderAccessibilityTest's providers. If a panel is ever rebuilt as a
     * table it must be registered there — this fails until it is.
     */
    public function test_the_dashboard_renders_no_table(): void
    {
        $this->fixture(2);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('<table', false);
    }
}
