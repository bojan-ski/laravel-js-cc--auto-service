<?php

namespace Tests\Feature\JobTemplates;

use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTemplateCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyRoleProvider(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    /**
     * Every write carries a valid payload: validation runs before the
     * controller authorizes, so an empty one would answer 302, not 403.
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function everyRoute(JobTemplate $template, JobTemplateItem $line): array
    {
        return [
            ['get', route('job-templates.index'), []],
            ['get', route('job-templates.create'), []],
            ['post', route('job-templates.store'), ['name' => 'A new template']],
            ['get', route('job-templates.edit', $template), []],
            ['put', route('job-templates.update', $template), ['name' => 'Renamed']],
            ['delete', route('job-templates.destroy', $template), []],
            ['patch', route('job-templates.restore', $template), []],
            ['post', route('job-templates.items.store', $template), ['type' => 'labor', 'line_description' => 'Oil service', 'quantity' => '1']],
            ['put', route('job-templates.items.update', [$template, $line]), ['line_description' => 'Oil service', 'quantity' => '1']],
            ['delete', route('job-templates.items.destroy', [$template, $line]), []],
        ];
    }

    public function test_a_mechanic_is_refused_every_template_route(): void
    {
        $template = JobTemplate::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->create();
        $mechanic = User::factory()->mechanic()->create();

        foreach ($this->everyRoute($template, $line) as [$method, $url, $payload]) {
            $this->actingAs($mechanic)->{$method}($url, $payload)->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_from_every_template_route(): void
    {
        $template = JobTemplate::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->create();

        foreach ($this->everyRoute($template, $line) as [$method, $url, $payload]) {
            $this->{$method}($url, $payload)->assertRedirect(route('login'));
        }
    }

    public function test_a_receptionist_may_not_delete_or_restore(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $template = JobTemplate::factory()->create();
        $trashed = JobTemplate::factory()->trashed()->create();

        $this->actingAs($receptionist)->delete(route('job-templates.destroy', $template))->assertForbidden();
        $this->actingAs($receptionist)->patch(route('job-templates.restore', $trashed))->assertForbidden();

        $this->assertFalse($template->fresh()->trashed());
        $this->assertTrue($trashed->fresh()->trashed());
    }

    #[DataProvider('everyRoleProvider')]
    public function test_a_trashed_template_is_not_found_on_every_route_but_restore(string $role): void
    {
        $template = JobTemplate::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->create();
        $template->delete();
        $user = User::factory()->{$role}()->create();

        $requests = [
            ['get', route('job-templates.edit', $template)],
            ['put', route('job-templates.update', $template)],
            ['delete', route('job-templates.destroy', $template)],
            ['post', route('job-templates.items.store', $template)],
            ['put', route('job-templates.items.update', [$template, $line])],
            ['delete', route('job-templates.items.destroy', [$template, $line])],
        ];

        foreach ($requests as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertNotFound();
        }
    }

    public function test_an_admin_restoring_an_active_template_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('job-templates.restore', JobTemplate::factory()->create()))
            ->assertForbidden();
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_creates_a_template_and_lands_on_its_edit_page(string $role): void
    {
        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('job-templates.store'), [
                'name' => 'Small service (petrol)',
                'description' => 'Oil, oil filter and air filter.',
            ]);

        $template = JobTemplate::query()->sole();

        $response->assertRedirect(route('job-templates.edit', $template))
            ->assertSessionHas('success', __('templates.flash.created', ['name' => 'Small service (petrol)']));

        $this->assertSame('Small service (petrol)', $template->name);
        $this->assertSame('Oil, oil filter and air filter.', $template->description);
    }

    public function test_the_name_is_required_and_bounded(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)
            ->post(route('job-templates.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($receptionist)
            ->post(route('job-templates.store'), ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');

        $this->actingAs($receptionist)
            ->post(route('job-templates.store'), ['name' => 'Valid', 'description' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, JobTemplate::withTrashed()->count());
    }

    public function test_a_duplicate_of_an_active_name_is_refused_with_the_generic_message(): void
    {
        JobTemplate::factory()->create(['name' => 'Front brake pads']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('job-templates.store'), ['name' => 'Front brake pads'])
            ->assertSessionHasErrors(['name' => __('validation.unique', ['attribute' => __('templates.fields.name')])]);
    }

    public function test_a_name_held_by_a_trashed_template_is_refused_with_its_own_message(): void
    {
        JobTemplate::factory()->trashed()->create(['name' => 'Small service (budget)']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('job-templates.store'), ['name' => 'Small service (budget)'])
            ->assertSessionHasErrors(['name' => __('templates.errors.name_trashed', ['name' => 'Small service (budget)'])]);

        $this->assertSame(1, JobTemplate::withTrashed()->count());
    }

    public function test_updating_keeps_its_own_name_and_renames(): void
    {
        $template = JobTemplate::factory()->create(['name' => 'Small service']);
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)
            ->put(route('job-templates.update', $template), ['name' => 'Small service', 'description' => 'Updated'])
            ->assertRedirect(route('job-templates.edit', $template))
            ->assertSessionHasNoErrors();

        $this->actingAs($receptionist)
            ->put(route('job-templates.update', $template), ['name' => 'Small service (petrol)'])
            ->assertSessionHas('success', __('templates.flash.updated', ['name' => 'Small service (petrol)']));

        $this->assertSame('Small service (petrol)', $template->fresh()->name);
    }

    public function test_renaming_onto_another_templates_name_is_refused(): void
    {
        JobTemplate::factory()->create(['name' => 'Taken']);
        $template = JobTemplate::factory()->create(['name' => 'Mine']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-templates.update', $template), ['name' => 'Taken'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Mine', $template->fresh()->name);
    }

    public function test_an_admin_deletes_and_restores_a_template_with_its_lines_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $template = JobTemplate::factory()->create(['name' => 'Small service']);
        $line = JobTemplateItem::factory()->forTemplate($template)->create(['description' => 'Oil service', 'quantity' => 1.25]);

        $this->actingAs($admin)
            ->delete(route('job-templates.destroy', $template))
            ->assertRedirect(route('job-templates.index'))
            ->assertSessionHas('success', __('templates.flash.deleted', ['name' => 'Small service']));

        $this->assertTrue($template->fresh()->trashed());
        $this->assertTrue($line->fresh() !== null);

        $this->actingAs($admin)
            ->patch(route('job-templates.restore', $template))
            ->assertRedirect(route('job-templates.index'))
            ->assertSessionHas('success', __('templates.flash.restored', ['name' => 'Small service']));

        $this->assertFalse($template->fresh()->trashed());
        $this->assertSame(
            [['Oil service', '1.25', 1]],
            $template->items()->get()->map(fn (JobTemplateItem $item): array => [$item->description, $item->quantity, $item->position])->all(),
        );
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_sees_the_templates_link_in_both_user_menus(string $role): void
    {
        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, 'href="'.route('job-templates.index').'"'));
    }

    public function test_a_mechanic_sees_no_templates_link_and_is_refused_the_route_anyway(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $html = $this->actingAs($mechanic)->get(route('parts.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('job-templates.index').'"', $html);
        $this->actingAs($mechanic)->get(route('job-templates.index'))->assertForbidden();
    }
}
