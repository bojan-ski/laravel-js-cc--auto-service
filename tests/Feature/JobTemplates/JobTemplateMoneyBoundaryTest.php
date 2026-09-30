<?php

namespace Tests\Feature\JobTemplates;

use App\Models\Job;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Asserted against the query log, never the markup: a page can render no
 * price and still carry one in memory. Every page here must read `parts`
 * through an explicit column list, and the guard below proves it did —
 * `select *` names no column, so a bare "not contains unit_price" would pass
 * vacuously on it.
 */
class JobTemplateMoneyBoundaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_the_edit_page_reads_parts_without_a_price_column(string $role): void
    {
        $template = $this->templateWithParts();

        foreach ([[], ['add' => 'part'], ['edit' => $template->items()->where('type', 'part')->value('id')]] as $query) {
            $selects = $this->partSelects($role, route('job-templates.edit', ['jobTemplate' => $template, ...$query]));

            $this->assertNotEmpty($selects, 'The edit page must read the lines\' parts.');

            foreach ($selects as $sql) {
                $this->assertStringNotContainsString('select *', $sql);
                $this->assertStringContainsString('`sku`', $sql);
                $this->assertStringNotContainsString('unit_price', $sql);
                $this->assertStringNotContainsString('cost_price', $sql);
            }
        }
    }

    public function test_the_edit_page_reads_the_part_picker_only_while_a_part_panel_shows(): void
    {
        Part::factory()->create();
        $template = JobTemplate::factory()->create();
        $labor = JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);

        foreach ([[], ['add' => 'labor'], ['edit' => $labor->getKey()]] as $query) {
            $this->assertSame([], $this->partSelects('receptionist', route('job-templates.edit', ['jobTemplate' => $template, ...$query])));
        }

        $this->assertCount(1, $this->partSelects('receptionist', route('job-templates.edit', ['jobTemplate' => $template, 'add' => 'part'])));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_the_index_and_create_pages_read_no_parts_at_all(string $role): void
    {
        $this->templateWithParts();

        $this->assertSame([], $this->partSelects($role, route('job-templates.index')));
        $this->assertSame([], $this->partSelects($role, route('job-templates.create')));
    }

    /**
     * The apply creates part lines through addPart(), so it inherits exactly
     * the two service-internal reads MoneyBoundaryTest accepts for a hand-added
     * part line: catalogPrice()'s scalar and StockService's locked `select *`.
     * Everything else it reads from `parts` must name its columns.
     */
    public function test_a_mechanics_apply_reads_parts_only_through_named_columns_and_the_two_accepted_reads(): void
    {
        $template = $this->templateWithParts();
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        DB::enableQueryLog();
        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertSessionHasNoErrors();
        $selects = $this->selectsFromParts();

        $stockLocks = array_values(array_filter($selects, fn (string $sql): bool => str_ends_with($sql, 'for update')));
        $priceReads = array_values(array_filter($selects, fn (string $sql): bool => str_contains($sql, 'unit_price')));
        $resolutions = array_values(array_diff($selects, $stockLocks, $priceReads));

        $this->assertCount(1, $stockLocks);
        $this->assertStringStartsWith('select * from `parts`', $stockLocks[0]);

        $this->assertCount(1, $priceReads);
        $this->assertMatchesRegularExpression('/^select `unit_price` from `parts`/', $priceReads[0]);

        $this->assertNotEmpty($resolutions, 'The apply must resolve the template\'s parts.');

        foreach ($resolutions as $sql) {
            $this->assertStringNotContainsString('select *', $sql);
            $this->assertStringContainsString('`sku`', $sql);
            $this->assertStringNotContainsString('cost_price', $sql);
            $this->assertStringNotContainsString('unit_price', $sql);
        }
    }

    private function templateWithParts(): JobTemplate
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($template)->forPart(Part::factory()->create(['cost_price' => '7.00']), 1)->create(['position' => 2]);

        return $template;
    }

    /**
     * @return list<string>
     */
    private function partSelects(string $role, string $url): array
    {
        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get($url)->assertOk();

        return $this->selectsFromParts();
    }

    /**
     * @return list<string>
     */
    private function selectsFromParts(): array
    {
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::flushQueryLog();
        DB::disableQueryLog();

        return array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from `parts`'),
        ));
    }
}
