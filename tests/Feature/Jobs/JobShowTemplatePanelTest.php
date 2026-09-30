<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobShowTemplatePanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    public function test_the_add_switch_offers_a_template_link_whose_name_contains_its_label(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('href="'.route('jobs.show', ['job' => $job, 'add' => 'template']).'#line-item-form"', false)
            ->assertSee('aria-label="'.__('templates.apply.link_label').'"', false)
            ->assertSee(__('templates.apply.link'));
    }

    public function test_a_mechanic_picks_a_template_and_sees_that_every_line_is_theirs(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->templateWithLines('Small service', 2);

        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertSee(__('templates.apply.title'))
            ->assertSee('action="'.route('jobs.templates.apply', $job).'"', false)
            ->assertSee(__('templates.apply.option', ['name' => 'Small service', 'lines' => '2 lines']))
            ->assertSee('<option value="'.$template->getKey().'"', false)
            ->assertSee('<p class="mt-2 text-sm text-text-soft">'.e(__('templates.apply.self_hint')).'</p>', false)
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee(__('templates.apply.manage'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<select\s+id="job_template_id"[^>]*>\s*<option value="">'.preg_quote(e(__('templates.apply.template_placeholder')), '/').'<\/option>/', $html);
        $this->assertSame(0, substr_count($html, ' selected>'));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_also_chooses_a_mechanic_described_by_the_hint(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->templateWithLines('Small service', 1);

        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertSee(__('templates.apply.mechanic_placeholder'))
            ->assertDontSee(__('templates.apply.self_hint'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<select\s+id="user_id"\s+name="user_id"[^>]*aria-describedby="user_id-hint"/', $html);
        $this->assertStringContainsString('id="user_id-hint"', $html);
    }

    public function test_a_missing_mechanic_renders_its_error_under_the_select_in_place_of_the_hint(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->templateWithLines('Small service', 1);
        $receptionist = User::factory()->receptionist()->create();
        $panel = route('jobs.show', ['job' => $job, 'add' => 'template']);

        $this->actingAs($receptionist)
            ->from($panel)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertRedirect($panel);

        $html = $this->actingAs($receptionist)->get($panel)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<select\s+id="user_id"\s+name="user_id"\s+aria-invalid="true"\s+aria-describedby="user_id-error"/', $html);
        $this->assertSame(1, substr_count($html, 'aria-describedby="user_id-'));
        $this->assertStringContainsString('<p id="user_id-error" class="mt-1 text-sm text-status-cancelled">'.e(__('templates.apply.errors.mechanic_required')).'</p>', $html);
    }

    public function test_the_picker_omits_deleted_and_empty_templates(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->templateWithLines('Kept', 1);
        $this->templateWithLines('Retired', 1)->delete();
        JobTemplate::factory()->create(['name' => 'Empty']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertSee('Kept')
            ->assertDontSee('Retired')
            ->assertDontSee('Empty —');
    }

    public function test_with_no_templates_a_mechanic_is_told_so_without_a_manage_link(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertSee(__('templates.apply.none'))
            ->assertDontSee(__('templates.apply.manage'))
            ->assertDontSee('action="'.route('jobs.templates.apply', $job).'"', false);
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_with_no_templates_front_office_gets_a_manage_link(string $role): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertSee(__('templates.apply.none'))
            ->assertSee(__('templates.apply.manage'));
    }

    public function test_no_panel_is_offered_on_a_job_that_takes_no_lines(): void
    {
        $job = Job::factory()->completed()->create();
        $this->templateWithLines('Small service', 1);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->assertDontSee(__('templates.apply.title'))
            ->assertDontSee('Small service');
    }

    public function test_the_job_page_reads_templates_only_while_the_template_panel_shows(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->templateWithLines('Small service', 1);
        $mechanic = User::factory()->mechanic()->create();

        $labor = $this->queriesFor($mechanic, route('jobs.show', ['job' => $job, 'add' => 'labor']));
        $template = $this->queriesFor($mechanic, route('jobs.show', ['job' => $job, 'add' => 'template']));

        $this->assertSame([], array_values(array_filter($labor, fn (string $sql): bool => str_contains($sql, 'job_templates'))));
        $this->assertCount(1, array_filter($template, fn (string $sql): bool => str_contains($sql, 'from `job_templates`')));
        $this->assertSame(count($labor) + 1, count($template));
    }

    /**
     * @return list<string>
     */
    private function queriesFor(User $user, string $url): array
    {
        DB::enableQueryLog();
        $this->actingAs($user)->get($url)->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $queries;
    }

    private function templateWithLines(string $name, int $lines): JobTemplate
    {
        $template = JobTemplate::factory()->create(['name' => $name]);

        foreach (range(1, $lines) as $position) {
            JobTemplateItem::factory()->forTemplate($template)->create(['position' => $position]);
        }

        return $template;
    }
}
