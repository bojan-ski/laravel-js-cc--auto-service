<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadsLineItemsTable;
use Tests\TestCase;

class JobShowLineItemsViewTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ReadsLineItemsTable;

    /**
     * job-items.update and job-items.destroy share a URI, so an `action="…"`
     * assertion cannot tell the edit form from the delete form. Only the edit
     * form spoofs PUT.
     */
    private const string UPDATE_METHOD_FIELD = 'name="_method" value="PUT"';

    public function test_the_edit_query_renders_that_line_as_a_form_and_hides_the_add_form(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]))
            ->assertOk()
            ->assertViewHas('editing', fn (?JobItem $editing): bool => $editing?->is($item) ?? false)
            ->assertSee(self::UPDATE_METHOD_FIELD, false)
            ->assertSee('action="'.route('job-items.update', $item).'"', false)
            ->assertSee(__('jobs.items.edit.title_for', ['description' => 'Brake service']))
            ->assertDontSee(__('jobs.items.add.title'))
            ->assertDontSee('action="'.route('jobs.items.store', $job).'"', false);
    }

    public function test_an_edit_query_for_a_line_the_viewer_may_not_edit_is_ignored(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]))
            ->assertOk()
            ->assertViewHas('editing', null)
            ->assertDontSee(self::UPDATE_METHOD_FIELD, false)
            ->assertSee(__('jobs.items.add.title'));
    }

    public function test_a_non_integer_edit_query_is_refused_rather_than_coerced(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job).'?edit=abc')
            ->assertSessionHasErrors('edit');
    }

    public function test_an_edit_query_of_zero_resolves_no_row(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job).'?edit=0')
            ->assertOk()
            ->assertViewHas('editing', null)
            ->assertDontSee(self::UPDATE_METHOD_FIELD, false)
            ->assertSee(__('jobs.items.add.title'));
    }

    public function test_the_edited_row_is_marked_for_assistive_technology_not_only_by_colour(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]))
            ->assertOk();

        $response->assertSee('aria-current="true"', false);
        $response->assertSee(__('jobs.items.edit.row_marker'));
        $response->assertSee(__('jobs.items.edit.title_for', ['description' => 'Brake service']));
    }

    public function test_the_edit_link_targets_the_form_and_the_who_select_is_marked_required(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $response->assertSee(route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]).'#line-item-form', false);
        $response->assertSee('id="line-item-form"', false);

        $this->assertMatchesRegularExpression(
            '/<select[^>]*\bname="user_id"[^>]*\brequired\b/s',
            $response->getContent(),
            'The "who performed the work" select is required by validation and must say so.',
        );
    }

    public function test_an_edit_query_naming_another_jobs_line_is_ignored(): void
    {
        $job = Job::factory()->inProgress()->create();
        $foreign = JobItem::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.show', ['job' => $job, 'edit' => $foreign->getKey()]))
            ->assertOk()
            ->assertViewHas('editing', null)
            ->assertDontSee(self::UPDATE_METHOD_FIELD, false);
    }

    public function test_a_mechanic_sees_edit_and_delete_only_on_their_own_line(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $own = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();
        $colleagues = JobItem::factory()->forJob($job)->create();

        $this->actingAs($mechanic)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(route('jobs.show', ['job' => $job, 'edit' => $own->getKey()]), false)
            ->assertSee('action="'.route('job-items.destroy', $own).'"', false)
            ->assertDontSee(route('jobs.show', ['job' => $job, 'edit' => $colleagues->getKey()]), false)
            ->assertDontSee('action="'.route('job-items.destroy', $colleagues).'"', false);
    }

    public function test_a_locked_job_renders_its_lines_with_no_controls(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);
        $job->locked_at = now();
        $job->save();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Brake service')
            ->assertDontSee(__('jobs.items.add.title'))
            ->assertDontSee('action="'.route('jobs.items.store', $job).'"', false)
            ->assertDontSee('action="'.route('job-items.destroy', $item).'"', false)
            ->assertDontSee(route('jobs.show', ['job' => $job, 'edit' => $item->getKey()]), false);
    }

    public function test_the_page_costs_the_same_number_of_queries_however_many_lines_it_has(): void
    {
        $this->assertSame(
            $this->queriesForJobWith(1),
            $this->queriesForJobWith(12),
            'Per-row authorization must not load the parent job once per line.',
        );
    }

    public function test_the_page_issues_exactly_one_select_against_the_jobs_table(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->count(12)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $jobSelects = array_filter(
            $queries,
            fn (array $query): bool => str_contains($query['query'], 'from `jobs`'),
        );

        $this->assertCount(1, $jobSelects, implode("\n", array_column($queries, 'query')));
    }

    public function test_the_part_picker_costs_exactly_one_extra_query_and_only_when_shown(): void
    {
        Part::factory()->count(4)->create();

        $plain = $this->queriesForJobWith(3);
        $addingLabor = $this->queriesForJobWith(3, ['add' => 'labor']);
        $addingParts = $this->queriesForJobWith(3, ['add' => 'part']);

        $this->assertSame($plain, $addingLabor, 'The labor form is the default; it must cost nothing extra.');
        $this->assertSame($plain + 1, $addingParts);
    }

    public function test_the_picker_query_is_skipped_when_the_viewer_may_not_add_lines(): void
    {
        Part::factory()->count(4)->create();

        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'part']))
            ->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame([], array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`'),
        )));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function queriesForJobWith(int $lines, array $query = []): int
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->count($lines)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', ['job' => $job, ...$query]))
            ->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    /**
     * Both directions read the table head rather than the whole page. "Price"
     * is a Faker surname, so a page-wide assertion fails for a customer called
     * Price when the column is absent — and passes for that same customer when
     * the column has been deleted.
     */
    public function test_a_mechanics_table_has_no_price_column_and_front_offices_does(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $mechanic = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.items.headers.who'));

        $this->assertStringNotContainsString(
            __('jobs.items.headers.price'),
            $this->lineItemsTableHead($mechanic),
        );

        $frontOffice = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $this->assertStringContainsString(
            __('jobs.items.headers.price'),
            $this->lineItemsTableHead($frontOffice),
        );
    }
}
