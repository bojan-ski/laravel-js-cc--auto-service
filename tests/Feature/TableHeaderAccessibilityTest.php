<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Below `md` every row becomes a CSS grid and several cells are hidden, so the
 * visible cells are a subset of the columns. ARIA maps headers to cells by
 * position: a header row exposing a column its body row hides mislabels every
 * cell after it. These assertions read class strings because the property under
 * test is CSS visibility, which has no runtime behaviour to observe and which
 * `TestCase`'s Vite stub deliberately puts out of reach of the built stylesheet.
 */
class TableHeaderAccessibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function page(string $url): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get($url)
            ->assertOk()
            ->getContent();
    }

    private function fixture(): Customer
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['year' => 2011]);
        Job::factory()->for($customer)->for($vehicle)->create(['mileage_in' => 184320]);
        Appointment::factory()->for($customer)->for($vehicle)->create([
            'scheduled_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        return $customer;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tableProvider(): array
    {
        return [
            'jobs board' => ['jobs.index'],
            'customers index' => ['customers.index'],
            'appointments index' => ['appointments.index'],
            'customer detail' => ['customers.show'],
            'vehicle detail' => ['vehicles.show'],
        ];
    }

    #[DataProvider('tableProvider')]
    public function test_every_table_head_is_screen_reader_visible_below_md(string $route): void
    {
        $customer = $this->fixture();

        $url = match ($route) {
            'customers.show' => route($route, $customer),
            'vehicles.show' => route($route, $customer->vehicles()->sole()),
            default => route($route),
        };

        $heads = $this->tableHeads($this->page($url));

        $this->assertNotEmpty($heads, "No <thead> was rendered on {$route}.");

        foreach ($heads as $thead) {
            preg_match('/<thead\b[^>]*>/', $thead, $tag);

            $this->assertStringContainsString('sr-only', $tag[0], "A <thead> on {$route} is hidden from assistive technology below md.");
            $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bhidden\b/', $tag[0], "A <thead> on {$route} still uses display:none below md.");
            $this->assertStringContainsString('md:not-sr-only', $tag[0], "A <thead> on {$route} would stay clipped to one pixel at desktop width.");
            $this->assertStringContainsString('md:table-header-group', $tag[0]);
        }
    }

    #[DataProvider('tableProvider')]
    public function test_every_visible_header_matches_a_visible_cell_below_md(string $route): void
    {
        $customer = $this->fixture();

        $url = match ($route) {
            'customers.show' => route($route, $customer),
            'vehicles.show' => route($route, $customer->vehicles()->sole()),
            default => route($route),
        };

        $html = $this->page($url);

        $headerCounts = array_map($this->countShownBelowMd(...), $this->tableHeads($html));
        $rowCounts = array_map($this->countShownBelowMd(...), $this->firstBodyRows($html));

        $this->assertNotEmpty($headerCounts, "No <thead> was rendered on {$route}.");
        $this->assertSame(
            $rowCounts,
            $headerCounts,
            "On {$route} a header row exposes a different number of columns below md than its body row exposes cells, so ARIA maps headers to the wrong cells."
        );
    }

    /**
     * A sticky <thead> only pins if no ancestor is a scroll container. The table
     * wrapper's `overflow-x-auto` makes `overflow-y` compute to `auto`, which
     * made it exactly that — so the header scrolled away at every desktop width
     * from spec 10 until this was measured in a browser. `md:overflow-x-visible`
     * turns the scroll container off above `md`, where sticky is the only thing
     * that wants it; below `md` the header is `sr-only` and horizontal scroll is
     * what matters, so `overflow-x-auto` stays.
     *
     * Class strings are all a request test can reach here. The behaviour itself
     * is only observable in a browser, where it was verified by scrolling past
     * the header's offset and measuring that its top clamps to 0.
     *
     * @return array<string, array{0: string}>
     */
    public static function stickyBoardProvider(): array
    {
        return [
            'jobs board' => ['jobs.index'],
            'appointments index' => ['appointments.index'],
        ];
    }

    #[DataProvider('stickyBoardProvider')]
    public function test_a_sticky_head_is_not_trapped_inside_a_scroll_container(string $route): void
    {
        $this->fixture();

        $html = $this->page(route($route));

        $heads = $this->tableHeads($html);
        $this->assertNotEmpty($heads, "No <thead> was rendered on {$route}.");

        foreach ($heads as $thead) {
            preg_match('/<thead\b[^>]*>/', $thead, $tag);
            $this->assertStringContainsString('md:sticky', $tag[0], "The {$route} head is expected to be sticky.");
        }

        $this->assertMatchesRegularExpression(
            '/<div[^>]*\bclass="[^"]*\boverflow-x-auto\b[^"]*\bmd:overflow-x-visible\b[^"]*"[^>]*>\s*<table\b/',
            $html,
            "On {$route} the table wrapper stays a scroll container at desktop width, so its sticky header cannot pin."
        );
    }

    /**
     * @return list<string>
     */
    private function tableHeads(string $html): array
    {
        preg_match_all('/<thead\b.*?<\/thead>/s', $html, $matches);

        return $matches[0];
    }

    /**
     * One representative body row per table, in the same order as the heads.
     *
     * @return list<string>
     */
    private function firstBodyRows(string $html): array
    {
        preg_match_all('/<tbody\b.*?<\/tbody>/s', $html, $bodies);

        $rows = [];

        foreach ($bodies[0] as $body) {
            if (preg_match('/<tr\b.*?<\/tr>/s', $body, $row) === 1) {
                $rows[] = $row[0];
            }
        }

        return $rows;
    }

    private function countShownBelowMd(string $markup): int
    {
        preg_match_all('/<(?:th|td)\b[^>]*>/', $markup, $cells);

        return count(array_filter(
            $cells[0],
            fn (string $cell): bool => ! preg_match('/class="[^"]*\bhidden\b/', $cell),
        ));
    }
}
