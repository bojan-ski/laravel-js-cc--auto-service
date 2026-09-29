<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\ReadsLineItemsTable;
use Tests\TestCase;

class JobLineDiscountViewTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsLineItemsTable;

    private function jobWithLines(string $state = 'inProgress'): Job
    {
        $job = Job::factory()->{$state}()->create();
        JobItem::factory()->forJob($job)->discounted('12.50')->create(['description' => 'Discounted work', 'quantity' => '1.00', 'unit_price' => '33.33']);
        JobItem::factory()->forJob($job)->create(['description' => 'Full price work', 'quantity' => '2.00', 'unit_price' => '100.00']);

        return $job;
    }

    private function jobPage(Job $job, string $role = 'receptionist', array $query = []): TestResponse
    {
        return $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', ['job' => $job, ...$query]))
            ->assertOk();
    }

    public function test_front_office_sees_the_discount_and_total_columns_and_the_mechanic_does_not(): void
    {
        $job = $this->jobWithLines();

        $head = $this->lineItemsTableHead($this->jobPage($job));

        $this->assertStringContainsString(__('jobs.items.headers.discount'), $head);
        $this->assertStringContainsString(__('jobs.items.headers.line_total'), $head);

        $mechanicHead = $this->lineItemsTableHead($this->jobPage($job, 'mechanic'));

        foreach (['price', 'discount', 'line_total'] as $header) {
            $this->assertStringNotContainsString(__("jobs.items.headers.{$header}"), $mechanicHead);
        }
    }

    public function test_a_row_shows_its_trimmed_percentage_and_its_discounted_total(): void
    {
        $response = $this->jobPage($this->jobWithLines());

        $response->assertSee(__('jobs.items.discount_value', ['percent' => '12.5']));
        $response->assertDontSee('12.50%');
        $response->assertSeeInOrder(['Discounted work', '33.33', '12.5%', '29.16']);
        $response->assertSeeInOrder(['Full price work', '100.00', __('app.value_none'), '200.00']);
    }

    public function test_the_footer_shows_the_discount_row_above_the_subtotal(): void
    {
        $job = $this->jobWithLines();

        $response = $this->jobPage($job)
            ->assertViewHas('discountTotal', '4.17')
            ->assertViewHas('subtotal', '229.16');

        $foot = $this->tableFoot($response);

        $this->assertMatchesRegularExpression(
            '/'.preg_quote(__('jobs.items.discount_total'), '/').'.*4\.17.*'.preg_quote(__('jobs.items.subtotal'), '/').'.*229\.16/s',
            $foot,
        );
    }

    public function test_an_undiscounted_job_has_no_discount_row(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $response = $this->jobPage($job)->assertViewHas('discountTotal', fn (mixed $value): bool => $value === null);

        $this->assertStringNotContainsString(__('jobs.items.discount_total'), $this->tableFoot($response));
        $this->assertSame(1, preg_match_all('/<tr\b/', $this->tableFoot($response)));
    }

    /**
     * The footer amount must sit under the Total column at every width. Below
     * md the Type and Disc. columns are display:none, and a hidden cell leaves
     * the table layout, so a single fixed colspan would shift the amount one
     * column right on a phone. Expanding each footer row by colspan must
     * reproduce the header's hidden-column pattern exactly, with the amount
     * starting on the Total column.
     */
    public function test_every_footer_row_mirrors_the_headers_hidden_columns(): void
    {
        $response = $this->jobPage($this->jobWithLines());

        $header = $this->cells($this->lineItemsTableHead($response));
        $headerHidden = array_map(fn (array $cell): bool => $cell['hidden'], $header);
        $totalColumn = array_search(true, array_map(
            fn (array $cell): bool => str_contains($cell['text'], __('jobs.items.headers.line_total')),
            $header,
        ), true);

        $this->assertCount(8, $header);
        $this->assertSame([true, false, false, false, true, false, false, false], $headerHidden);

        preg_match_all('/<tr\b.*?<\/tr>/s', $this->tableFoot($response), $rows);
        $this->assertCount(2, $rows[0]);

        foreach ($rows[0] as $row) {
            $expanded = [];
            $amountStart = null;

            foreach ($this->cells($row) as $cell) {
                if (str_contains($cell['text'], '.')) {
                    $amountStart = count($expanded);
                }

                $expanded = [...$expanded, ...array_fill(0, $cell['colspan'], $cell['hidden'])];
            }

            $this->assertSame($headerHidden, $expanded, "Footer row misaligned:\n{$row}");
            $this->assertSame($totalColumn, $amountStart, "Footer amount is not under Total:\n{$row}");
        }
    }

    public function test_the_discount_control_links_to_the_panel_and_names_its_line(): void
    {
        $job = $this->jobWithLines();
        $item = $job->items()->firstOrFail();

        $response = $this->jobPage($job);

        $response->assertSee(
            e(route('jobs.show', ['job' => $job, 'discount' => $item->getKey()]).'#line-discount-form'),
            false,
        );

        $name = __('jobs.items.discount.open_line', ['description' => 'Discounted work']);
        $this->assertStringStartsWith(__('jobs.items.discount.open'), $name);
        $response->assertSee('aria-label="'.e($name).'"', false);
    }

    public function test_a_completed_unlocked_job_shows_only_the_discount_control(): void
    {
        $job = $this->jobWithLines('completed');
        $item = $job->items()->firstOrFail();

        $response = $this->jobPage($job);

        $response->assertSee('discount='.$item->getKey(), false);
        $response->assertDontSee('edit='.$item->getKey(), false);
        $response->assertDontSee(__('jobs.items.delete.label_line', ['description' => 'Discounted work']));
    }

    public function test_no_discount_control_on_a_locked_job_or_for_a_mechanic(): void
    {
        $job = $this->jobWithLines('completed');
        $item = $job->items()->firstOrFail();

        $this->jobPage($job, 'mechanic')->assertDontSee('discount='.$item->getKey(), false);

        $job->forceFill(['locked_at' => now()->subMinutes(5)])->saveQuietly();

        $this->jobPage($job, 'admin')->assertDontSee('discount='.$item->getKey(), false);
    }

    public function test_the_panel_opens_for_the_chosen_line_and_marks_its_row(): void
    {
        $job = $this->jobWithLines();
        $item = $job->items()->firstOrFail();

        $response = $this->jobPage($job, 'receptionist', ['discount' => $item->getKey()])
            ->assertViewHas('discounting', fn (?JobItem $discounting): bool => $discounting?->is($item) === true);

        $html = $response->getContent();

        $this->assertSame(1, preg_match('/<form id="line-discount-form"[^>]*>.*?<\/form>/s', $html, $form));
        $panel = $form[0];

        $this->assertStringContainsString('action="'.e(route('job-items.discount.update', $item)).'"', $panel);
        $this->assertStringContainsString('name="_method" value="PATCH"', $panel);
        $this->assertStringContainsString(e(__('jobs.items.discount.title_for', ['description' => 'Discounted work'])), $panel);
        $this->assertMatchesRegularExpression('/<input[^>]*name="discount_percent"[^>]*value="12\.50"/s', $panel);
        $this->assertMatchesRegularExpression('/<input[^>]*name="discount_percent"[^>]*aria-describedby="discount_percent-hint"/s', $panel);
        $this->assertStringContainsString('id="discount_percent-hint"', $panel);
        $this->assertStringContainsString(e(__('jobs.items.discount.hint')), $panel);
        $this->assertStringContainsString(e(__('jobs.items.discount.save')), $panel);

        $this->assertMatchesRegularExpression(
            '/<tr[^>]*aria-current="true"[^>]*>(?:(?!<\/tr>).)*Discounted work(?:(?!<\/tr>).)*'.preg_quote(e(__('jobs.items.discount.row_marker')), '/').'/s',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
    }

    public function test_edit_wins_over_discount_and_discount_wins_over_add(): void
    {
        $job = $this->jobWithLines();
        [$first, $second] = $job->items()->orderBy('id')->get()->all();

        $this->jobPage($job, 'admin', ['edit' => $first->getKey(), 'discount' => $second->getKey()])
            ->assertViewHas('discounting', fn (mixed $value): bool => $value === null)
            ->assertSee('id="line-item-form"', false)
            ->assertDontSee('id="line-discount-form"', false);

        $this->jobPage($job, 'admin', ['discount' => $second->getKey(), 'add' => 'part'])
            ->assertViewHas('showPartForm', false)
            ->assertSee('id="line-discount-form"', false)
            ->assertDontSee('id="line-item-form"', false);
    }

    public function test_an_edit_id_that_resolves_to_nothing_does_not_block_the_panel(): void
    {
        $job = $this->jobWithLines();
        $item = $job->items()->firstOrFail();

        $this->jobPage($job, 'receptionist', ['edit' => 999999, 'discount' => $item->getKey()])
            ->assertSee('id="line-discount-form"', false);
    }

    public function test_an_unusable_discount_id_renders_no_panel_and_no_error(): void
    {
        $job = $this->jobWithLines();
        $elsewhere = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->create();

        $this->jobPage($job, 'receptionist', ['discount' => $elsewhere->getKey()])
            ->assertViewHas('discounting', fn (mixed $value): bool => $value === null)
            ->assertDontSee('id="line-discount-form"', false);

        $this->jobPage($job, 'receptionist', ['discount' => 999999])
            ->assertViewHas('discounting', fn (mixed $value): bool => $value === null)
            ->assertDontSee('id="line-discount-form"', false);
    }

    public function test_a_non_integer_discount_query_is_refused_rather_than_coerced(): void
    {
        $job = $this->jobWithLines();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job).'?discount=abc')
            ->assertSessionHasErrors('discount');
    }

    public function test_the_panel_does_not_open_on_a_locked_job(): void
    {
        $job = $this->jobWithLines('completed');
        $item = $job->items()->firstOrFail();
        $job->forceFill(['locked_at' => now()->subMinutes(5)])->saveQuietly();

        $this->jobPage($job, 'admin', ['discount' => $item->getKey()])
            ->assertViewHas('discounting', fn (mixed $value): bool => $value === null)
            ->assertDontSee('id="line-discount-form"', false);
    }

    private function tableFoot(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('/<tfoot\b.*?<\/tfoot>/s', $response->getContent(), $foot), 'The line-items footer did not render.');

        return $foot[0];
    }

    /**
     * @return list<array{hidden: bool, colspan: int, text: string}>
     */
    private function cells(string $html): array
    {
        preg_match_all('/<(td|th)\b([^>]*)>(.*?)<\/\1>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'hidden' => preg_match('/class="[^"]*\bhidden\b[^"]*"/', $match[2]) === 1 && str_contains($match[2], 'md:table-cell'),
            'colspan' => preg_match('/colspan="(\d+)"/', $match[2], $span) === 1 ? (int) $span[1] : 1,
            'text' => trim(strip_tags($match[3])),
        ], $matches);
    }
}
