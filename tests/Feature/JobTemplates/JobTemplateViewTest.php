<?php

namespace Tests\Feature\JobTemplates;

use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsRowLinks;
use Tests\TestCase;

class JobTemplateViewTest extends TestCase
{
    use AssertsRowLinks;
    use LazilyRefreshDatabase;

    public function test_the_list_shows_each_template_as_a_whole_row_link_with_its_line_count(): void
    {
        $service = JobTemplate::factory()->create(['name' => 'Small service', 'description' => 'Oil and filters']);
        JobTemplateItem::factory()->forTemplate($service)->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($service)->create(['position' => 2]);
        $empty = JobTemplate::factory()->create(['name' => 'Empty one']);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.index'))
            ->assertOk()
            ->assertSeeInOrder(['Empty one', __('templates.no_lines'), 'Small service', 'Oil and filters', '2 lines'])
            ->getContent();

        $this->assertRowIsOneLinkTo($html, route('job-templates.edit', $service));
        $this->assertRowIsOneLinkTo($html, route('job-templates.edit', $empty));
        $this->assertStringNotContainsString(__('templates.index.headers.actions'), $html);
    }

    public function test_a_receptionist_does_not_see_deleted_templates(): void
    {
        JobTemplate::factory()->trashed()->create(['name' => 'Retired template']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.index'))
            ->assertOk()
            ->assertDontSee('Retired template')
            ->assertSee(__('templates.empty.none.title'));
    }

    public function test_an_admin_sees_a_deleted_template_marked_with_restore_and_no_link(): void
    {
        $trashed = JobTemplate::factory()->trashed()->create(['name' => 'Retired template']);
        JobTemplate::factory()->create(['name' => 'Active template']);

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('job-templates.index'))
            ->assertOk()
            ->assertSee(__('templates.index.headers.actions'))
            ->assertSee(__('templates.trashed.badge'))
            ->assertSee('aria-label="'.e(__('templates.restore.action', ['name' => 'Retired template'])).'"', false)
            ->getContent();

        $this->assertStringContainsString('action="'.route('job-templates.restore', $trashed).'"', $html);
        $this->assertStringNotContainsString('href="'.route('job-templates.edit', $trashed).'"', $html);
    }

    public function test_an_admin_sees_no_actions_column_while_no_template_is_deleted(): void
    {
        JobTemplate::factory()->create(['name' => 'Active template']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('job-templates.index'))
            ->assertOk()
            ->assertSee('Active template')
            ->assertDontSee(__('templates.index.headers.actions'));
    }

    public function test_the_empty_list_offers_a_new_template(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.index'))
            ->assertOk()
            ->assertSee(__('templates.empty.none.title'))
            ->assertSee('href="'.route('job-templates.create').'"', false);
    }

    public function test_the_edit_page_without_lines_says_so_and_opens_the_labor_panel(): void
    {
        $template = JobTemplate::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', $template))
            ->assertOk()
            ->assertSee(__('templates.lines.empty'))
            ->assertSee(__('templates.lines.add.labor_title'))
            ->assertSee('name="line_description"', false)
            ->assertSee('name="type" value="labor"', false)
            ->assertDontSee('name="part_id"', false);
    }

    public function test_the_part_panel_lists_active_parts_and_posts_a_part_line(): void
    {
        $template = JobTemplate::factory()->create();
        Part::factory()->create(['name' => 'Air filter', 'sku' => 'AF-1101']);
        Part::factory()->create(['name' => 'Retired filter'])->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', ['jobTemplate' => $template, 'add' => 'part']))
            ->assertOk()
            ->assertSee(__('templates.lines.add.part_title'))
            ->assertSee('name="type" value="part"', false)
            ->assertSee('Air filter')
            ->assertDontSee('Retired filter')
            ->assertDontSee('name="line_description"', false);
    }

    public function test_editing_a_line_marks_its_row_and_fills_the_form(): void
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->create(['position' => 1, 'description' => 'Other line']);
        $line = JobTemplateItem::factory()->forTemplate($template)->create(['position' => 2, 'description' => 'Oil service', 'quantity' => 1.25]);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', ['jobTemplate' => $template, 'edit' => $line->getKey()]))
            ->assertOk()
            ->assertSee(__('templates.lines.edit.title_for', ['description' => 'Oil service']))
            ->assertSee('value="Oil service"', false)
            ->assertSee('value="1.25"', false)
            ->assertSee('action="'.route('job-templates.items.update', [$template, $line]).'"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>(?:(?!<\/tr>).)*Oil service/s', $html);
        $this->assertStringContainsString(__('templates.lines.edit.row_marker'), $html);
    }

    public function test_editing_a_part_line_shows_whole_number_quantity(): void
    {
        $template = JobTemplate::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->forPart(Part::factory()->create(), 3)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', ['jobTemplate' => $template, 'edit' => $line->getKey()]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="quantity"\s+type="number"\s+value="3"/', $html);
    }

    public function test_editing_a_part_line_keeps_its_part_selected(): void
    {
        $template = JobTemplate::factory()->create();
        Part::factory()->create(['name' => 'Another part']);
        $part = Part::factory()->create(['name' => 'Air filter']);
        $line = JobTemplateItem::factory()->forTemplate($template)->forPart($part, 1)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', ['jobTemplate' => $template, 'edit' => $line->getKey()]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, ' selected>'));
        $this->assertMatchesRegularExpression('/<option value="'.$part->getKey().'"\s+selected>/', $html);
    }

    public function test_editing_a_line_whose_part_was_deleted_selects_nothing(): void
    {
        $template = JobTemplate::factory()->create();
        Part::factory()->create(['name' => 'Active part']);
        $part = Part::factory()->create(['name' => 'Retired part']);
        $line = JobTemplateItem::factory()->forTemplate($template)->forPart($part, 1)->create();
        $part->delete();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', ['jobTemplate' => $template, 'edit' => $line->getKey()]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<option value="'.Part::query()->where('name', 'Active part')->value('id').'"', $html);
        $this->assertSame(0, substr_count($html, ' selected>'));
    }

    public function test_each_row_names_its_line_in_the_edit_and_remove_labels(): void
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->create(['position' => 1, 'description' => 'Oil service']);
        JobTemplateItem::factory()->forTemplate($template)->forPart(Part::factory()->create(['name' => 'Air filter']), 1)->create(['position' => 2]);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', $template))
            ->assertOk();

        foreach (['Oil service', 'Air filter'] as $name) {
            $response
                ->assertSee('aria-label="'.e(__('templates.lines.edit.open_line', ['description' => $name])).'"', false)
                ->assertSee('aria-label="'.e(__('templates.lines.remove.action', ['description' => $name])).'"', false);
        }
    }

    public function test_a_line_whose_part_was_deleted_is_marked_with_a_notice(): void
    {
        $template = JobTemplate::factory()->create();
        $part = Part::factory()->create(['name' => 'Oil filter (discontinued)', 'sku' => 'OF-0999']);
        JobTemplateItem::factory()->forTemplate($template)->forPart($part, 1)->create();
        $part->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', $template))
            ->assertOk()
            ->assertSee(__('templates.lines.part_label', ['name' => 'Oil filter (discontinued)', 'sku' => 'OF-0999']))
            ->assertSee(__('templates.lines.part_deleted'))
            ->assertSee('role="note"', false)
            ->assertSee(__('templates.lines.deleted_part_notice'));
    }

    public function test_no_notice_while_every_part_is_active(): void
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->forPart(Part::factory()->create(), 1)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', $template))
            ->assertOk()
            ->assertDontSee(__('templates.lines.deleted_part_notice'))
            ->assertDontSee(__('templates.lines.part_deleted'));
    }

    public function test_a_failed_line_save_stays_in_the_line_form_and_leaves_the_template_description_alone(): void
    {
        $template = JobTemplate::factory()->create(['description' => 'The template itself']);
        $receptionist = User::factory()->receptionist()->create();
        $edit = route('job-templates.edit', $template);

        $this->actingAs($receptionist)
            ->from($edit)
            ->post(route('job-templates.items.store', $template), ['type' => 'labor', 'line_description' => 'Typed line', 'quantity' => '0'])
            ->assertRedirect($edit);

        $html = $this->actingAs($receptionist)->get($edit)->assertOk()->getContent();

        $this->assertStringContainsString('id="quantity-error"', $html);
        $this->assertStringContainsString('value="Typed line"', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="description"[^>]*>The template itself<\/textarea>/', $html);
        $this->assertStringNotContainsString('id="description-error"', $html);
        $this->assertSame(1, substr_count($html, 'id="description"'));
    }

    public function test_only_an_admin_sees_the_delete_control(): void
    {
        $template = JobTemplate::factory()->create(['name' => 'Small service']);
        $label = 'aria-label="'.e(__('templates.delete.action', ['name' => 'Small service'])).'"';

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.edit', $template))
            ->assertDontSee($label, false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('job-templates.edit', $template))
            ->assertSee($label, false)
            ->assertSee('action="'.route('job-templates.destroy', $template).'"', false);
    }

    public function test_the_create_page_renders_the_form(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('job-templates.create'))
            ->assertOk()
            ->assertSee(__('templates.create.title'))
            ->assertSee('action="'.route('job-templates.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false);
    }
}
