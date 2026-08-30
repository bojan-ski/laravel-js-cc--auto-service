<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartMovementHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function section(Part $part, string $role = 'receptionist'): string
    {
        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->getContent();

        preg_match(
            '/<section[^>]*>(?:(?!<\/section>).)*'.preg_quote(__('parts.show.movements'), '/').'.*?<\/section>/s',
            $html,
            $match,
        );

        $this->assertNotEmpty($match, 'No movements section was rendered.');

        return $match[0];
    }

    /**
     * @return list<string>
     */
    private function rows(string $section): array
    {
        preg_match_all('/<tr\b(?:(?!<\/tr>).)*<\/tr>/s', $section, $matches);

        return array_values(array_filter(
            $matches[0],
            fn (string $row): bool => ! str_contains($row, '<th'),
        ));
    }

    public function test_the_section_lists_movements_newest_first(): void
    {
        $part = Part::factory()->create();

        StockMovement::factory()->forPart($part)->restock(11)->create();
        StockMovement::factory()->forPart($part)->manualAdjustment(-7)->create();
        StockMovement::factory()->forPart($part)->restock(33)->create();

        $rows = $this->rows($this->section($part));

        $this->assertCount(3, $rows);
        $this->assertStringContainsString('+33', $rows[0]);
        $this->assertStringContainsString('-7', $rows[1]);
        $this->assertStringContainsString('+11', $rows[2]);
    }

    /**
     * Asserted against the literal the spec fixes, not against the constant.
     * Every other cap assertion reads HISTORY_LIMIT, so all of them would follow
     * the constant wherever it moved and none would notice the change.
     */
    public function test_the_history_shows_the_twenty_most_recent_movements(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->count(24)->create();

        $this->assertSame(20, StockService::HISTORY_LIMIT);
        $this->assertCount(20, $this->rows($this->section($part)));
        $this->assertStringContainsString('Showing 20 of 24 movements', $this->section($part));
    }

    public function test_the_section_caps_at_the_history_limit_and_says_how_many_exist(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->count(StockService::HISTORY_LIMIT + 4)->create();

        $section = $this->section($part);

        $this->assertCount(StockService::HISTORY_LIMIT, $this->rows($section));
        $this->assertStringContainsString(
            __('parts.movements.showing', [
                'shown' => StockService::HISTORY_LIMIT,
                'total' => StockService::HISTORY_LIMIT + 4,
            ]),
            $section,
        );
    }

    public function test_the_count_line_is_absent_when_nothing_is_hidden(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->count(StockService::HISTORY_LIMIT)->create();

        $section = $this->section($part);

        $this->assertCount(StockService::HISTORY_LIMIT, $this->rows($section));
        $this->assertStringNotContainsString('of '.StockService::HISTORY_LIMIT.' movements', $section);
    }

    public function test_a_movement_with_no_line_item_renders_its_reason_without_a_link(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->restock(5)->create();

        $row = $this->rows($this->section($part))[0];

        $this->assertStringContainsString(__('parts.reasons.restock'), $row);
        $this->assertStringNotContainsString('<a ', $row);
    }

    public function test_a_movement_whose_line_item_survives_links_to_its_job(): void
    {
        $part = Part::factory()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        StockMovement::factory()->forPart($part)->jobUse(2)->forJobItem($item)->create();

        $row = $this->rows($this->section($part))[0];

        $this->assertStringContainsString(__('parts.reasons.job_use'), $row);
        $this->assertStringContainsString(route('jobs.show', $job).'"', $row);
        $this->assertStringContainsString($job->reference, $row);
    }

    public function test_a_movement_outliving_its_line_item_renders_without_a_link_and_without_error(): void
    {
        $part = Part::factory()->create();
        $item = JobItem::factory()->create();
        StockMovement::factory()->forPart($part)->jobUse(2)->forJobItem($item)->create();

        DB::table('job_items')->where('id', $item->getKey())->delete();

        $row = $this->rows($this->section($part))[0];

        $this->assertStringContainsString(__('parts.reasons.job_use'), $row);
        $this->assertStringNotContainsString('<a ', $row);
    }

    /**
     * The two tests above build their fixtures with StockMovementFactory. These two
     * drive the real path instead: until spec 16 nothing in the application wrote a
     * movement carrying a job_item_id, so the link had never been exercised end to end.
     */
    public function test_a_movement_written_by_adding_a_part_line_links_to_its_job(): void
    {
        $actor = User::factory()->receptionist()->create();
        $part = Part::factory()->create();
        $job = Job::factory()->inProgress()->create();

        app(StockService::class)->record($part, 10, StockMovementReason::Restock, $actor);
        app(JobItemService::class)->addPart($job, $part, $actor, ['quantity' => 2]);

        $row = $this->rows($this->section($part))[0];

        $this->assertStringContainsString(__('parts.reasons.job_use'), $row);
        $this->assertStringContainsString(route('jobs.show', $job).'"', $row);
        $this->assertStringContainsString($job->reference, $row);
    }

    public function test_a_return_outlives_the_line_deletion_that_caused_it_and_renders_without_a_link(): void
    {
        $actor = User::factory()->receptionist()->create();
        $part = Part::factory()->create();
        $job = Job::factory()->inProgress()->create();

        app(StockService::class)->record($part, 10, StockMovementReason::Restock, $actor);
        $item = app(JobItemService::class)->addPart($job, $part, $actor, ['quantity' => 2]);
        app(JobItemService::class)->delete($item, $actor);

        $rows = $this->rows($this->section($part));

        $this->assertCount(3, $rows);
        $this->assertStringContainsString(__('parts.reasons.job_return'), $rows[0]);
        $this->assertStringNotContainsString('<a ', $rows[0]);
        $this->assertStringNotContainsString('<a ', $rows[1]);
    }

    public function test_a_note_renders_beneath_its_reason_inside_the_same_row(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->manualAdjustment(-3)->create();

        $rows = $this->rows($this->section($part));

        $this->assertCount(1, $rows, 'A note must not add a second row, which would desync the header/cell mapping.');
        $this->assertStringContainsString('count correction', $rows[0]);
    }

    public function test_the_person_who_caused_the_movement_is_named_even_after_offboarding(): void
    {
        $part = Part::factory()->create();
        $user = User::factory()->receptionist()->create(['name' => 'Jelena Markovic']);
        StockMovement::factory()->forPart($part)->causedBy($user)->create();
        $user->delete();

        $row = $this->rows($this->section($part))[0];

        $this->assertStringContainsString('Jelena Markovic', $row);
        $this->assertStringContainsString(__('app.deleted'), $row);
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

    #[DataProvider('roleProvider')]
    public function test_every_role_sees_the_whole_ledger(string $role): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->restock(11)->create();
        StockMovement::factory()->forPart($part)->manualAdjustment(-7)->create();

        $rows = $this->rows($this->section($part, $role));

        $this->assertCount(2, $rows);
        $this->assertStringContainsString('+11', $rows[0].$rows[1]);
        $this->assertStringContainsString('-7', $rows[0].$rows[1]);
    }

    public function test_a_mechanic_is_offered_no_adjust_control(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->create();

        $this->assertStringNotContainsString(
            route('parts.adjust.create', $part),
            $this->section($part, 'mechanic'),
        );
    }

    #[DataProvider('roleProvider')]
    public function test_no_monetary_value_appears_in_the_section(string $role): void
    {
        $part = Part::factory()->create(['unit_price' => 1234.56, 'cost_price' => 987.65]);
        StockMovement::factory()->forPart($part)->restock(11)->create();

        $section = $this->section($part, $role);

        $this->assertStringNotContainsString('1,234.56', $section);
        $this->assertStringNotContainsString('987.65', $section);
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d{2}/', strip_tags($section));
    }

    /**
     * Five: the route binding, the visibility-scoped re-resolve, the capped
     * movement list, its users, and the total. The `jobItem` eager load costs
     * nothing here — Laravel skips a belongs-to eager query outright when every
     * foreign key is null, which is every movement until spec 16.
     */
    #[DataProvider('roleProvider')]
    public function test_the_page_issues_a_bounded_number_of_queries(string $role): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->count(StockService::HISTORY_LIMIT + 4)->create();

        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('parts.show', $part));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(5, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_a_page_of_job_linked_movements_stays_bounded(): void
    {
        $part = Part::factory()->create();
        $job = Job::factory()->inProgress()->create();

        foreach (range(1, StockService::HISTORY_LIMIT) as $ignored) {
            StockMovement::factory()
                ->forPart($part)
                ->jobUse()
                ->forJobItem(JobItem::factory()->forJob($job)->create())
                ->create();
        }

        $user = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('parts.show', $part));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(7, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_the_row_count_does_not_change_the_query_count(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->create();
        $user = User::factory()->admin()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('parts.show', $part))->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        StockMovement::factory()->forPart($part)->count(15)->create();
        DB::flushQueryLog();

        $this->actingAs($user)->get(route('parts.show', $part))->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large, 'The movement history issues a query per row.');
    }
}
