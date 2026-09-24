<?php

namespace Tests\Feature\JobItems;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobPartLineViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string UPDATE_METHOD_FIELD = 'name="_method" value="PUT"';

    public function test_no_parameter_renders_the_labor_form_only(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->page($job)
            ->assertSee('action="'.route('jobs.items.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.parts.store', $job).'"', false)
            ->assertSee(__('jobs.items.add.title'))
            ->assertDontSee(__('jobs.items.add.part_title'));
    }

    public function test_the_part_parameter_renders_the_part_form_only(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart();

        $this->page($job, ['add' => 'part'])
            ->assertSee('action="'.route('jobs.parts.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.items.store', $job).'"', false)
            ->assertSee(__('jobs.items.add.part_title'))
            ->assertDontSee(__('jobs.items.add.title'));
    }

    public function test_an_empty_catalogue_explains_itself_instead_of_an_unusable_form(): void
    {
        $job = Job::factory()->inProgress()->create();
        Part::factory()->create()->delete();

        $this->page($job, ['add' => 'part'])
            ->assertSee(__('jobs.items.add.no_parts'))
            ->assertDontSee('action="'.route('jobs.parts.store', $job).'"', false)
            ->assertDontSee('name="part_id"', false);
    }

    public function test_an_unrecognised_add_parameter_falls_back_to_labor(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->page($job, ['add' => 'nonsense'])
            ->assertOk()
            ->assertViewHas('adding', 'labor')
            ->assertSee('action="'.route('jobs.items.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.parts.store', $job).'"', false);
    }

    public function test_the_edit_parameter_wins_over_the_add_parameter(): void
    {
        $item = $this->partLine();
        $job = $item->job;

        $this->page($job, ['add' => 'part', 'edit' => $item->getKey()])
            ->assertSee(self::UPDATE_METHOD_FIELD, false)
            ->assertDontSee('action="'.route('jobs.parts.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.items.store', $job).'"', false);
    }

    public function test_the_add_links_mark_the_active_one(): void
    {
        $job = Job::factory()->inProgress()->create();

        $labor = route('jobs.show', ['job' => $job, 'add' => 'labor']).'#line-item-form';
        $part = route('jobs.show', ['job' => $job, 'add' => 'part']).'#line-item-form';

        $onLabor = $this->page($job)->getContent();
        $onPart = $this->page($job, ['add' => 'part'])->getContent();

        $this->assertStringContainsString('aria-current="page"', $this->anchor($onLabor, $labor));
        $this->assertStringNotContainsString('aria-current="page"', $this->anchor($onLabor, $part));

        $this->assertStringContainsString('aria-current="page"', $this->anchor($onPart, $part));
        $this->assertStringNotContainsString('aria-current="page"', $this->anchor($onPart, $labor));
    }

    public function test_the_add_switch_sits_inside_the_form_it_controls(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart();

        $part = e(route('jobs.show', ['job' => $job, 'add' => 'part']).'#line-item-form');

        foreach ([[], ['add' => 'part']] as $query) {
            $html = $this->page($job, $query)->getContent();

            preg_match('/<form id="line-item-form".*?<\/form>/s', $html, $form);

            $this->assertNotEmpty($form, 'The add form did not render.');
            $this->assertStringContainsString($part, $form[0]);
            $this->assertSame(1, substr_count($html, $part), 'The switch must render once, inside the form.');
        }
    }

    public function test_the_edit_form_offers_no_add_switch(): void
    {
        $item = $this->partLine();

        $this->page($item->job, ['edit' => $item->getKey()])
            ->assertSee(self::UPDATE_METHOD_FIELD, false)
            ->assertDontSee(__('jobs.items.add.labor_link_label'))
            ->assertDontSee(__('jobs.items.add.part_link_label'));
    }

    public function test_an_empty_catalogue_still_offers_the_way_back_to_labor(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->page($job, ['add' => 'part'])
            ->assertSee(__('jobs.items.add.no_parts'))
            ->assertSee('id="line-item-form"', false)
            ->assertSee(e(route('jobs.show', ['job' => $job, 'add' => 'labor']).'#line-item-form'), false);
    }

    private function anchor(string $html, string $href): string
    {
        preg_match('/<a\b[^>]*'.preg_quote(e($href), '/').'[^>]*>/', $html, $match);

        $this->assertNotEmpty($match, "No anchor was rendered for {$href}.");

        return $match[0];
    }

    public function test_the_picker_lists_parts_with_their_stock_and_shelf(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(['sku' => 'BP-2210', 'name' => 'Brake pads front', 'location' => 'B1']);

        $this->page($job, ['add' => 'part'])
            ->assertSee('BP-2210 · Brake pads front · 10 in stock · shelf B1')
            ->assertSee(__('parts.picker.placeholder'))
            ->assertSee('value="'.$part->getKey().'"', false);
    }

    public function test_the_picker_omits_soft_deleted_parts(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart(['name' => 'Still stocked']);
        Part::factory()->create(['name' => 'Retired part'])->delete();

        $this->page($job, ['add' => 'part'])
            ->assertSee('Still stocked')
            ->assertDontSee('Retired part');
    }

    public function test_a_mechanic_sees_the_picker_but_no_price_field(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart(['name' => 'Brake pads front', 'unit_price' => '4200.00']);

        $this->page($job, ['add' => 'part'], 'mechanic')
            ->assertSee('Brake pads front')
            ->assertDontSee(__('jobs.items.add.price'))
            ->assertDontSee('name="unit_price"', false)
            ->assertDontSee('4,200.00')
            ->assertDontSee('4200.00');
    }

    public function test_front_office_sees_the_price_override_field(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart();

        $this->page($job, ['add' => 'part'])
            ->assertSee(__('jobs.items.add.price'))
            ->assertSee('name="unit_price"', false);
    }

    public function test_a_part_quantity_renders_without_decimals_while_labor_keeps_them(): void
    {
        $job = Job::factory()->inProgress()->create();

        JobItem::factory()->forJob($job)->forPart(Part::factory()->create())->create(['quantity' => 2]);
        JobItem::factory()->forJob($job)->create(['quantity' => 1.5]);

        $content = $this->page($job)->getContent();

        $this->assertStringContainsString('>2</span>', $content);
        $this->assertStringContainsString('>1.5</span>', $content);
        $this->assertStringNotContainsString('>2.00</span>', $content);
    }

    /**
     * The quantity column is decimal(8,2), so an unguarded $item->quantity renders
     * "2.00" — which Laravel's integer rule rejects via FILTER_VALIDATE_INT. Round
     * tripping the value the form actually offers is the only assertion that catches it.
     */
    public function test_the_rendered_edit_form_offers_a_quantity_the_validator_accepts(): void
    {
        $item = $this->partLine(quantity: 2);

        $input = $this->quantityInput($this->page($item->job, ['edit' => $item->getKey()])->getContent());

        $this->assertStringContainsString('value="2"', $input);
        $this->assertStringContainsString('step="1"', $input);
        $this->assertStringContainsString('min="1"', $input);

        $form = $this->page($item->job, ['edit' => $item->getKey()])->getContent();

        $this->assertStringNotContainsString('required', $this->control($form, 'description'));
        $this->assertStringNotContainsString('required', $this->control($form, 'user_id'));

        preg_match('/value="([^"]*)"/', $input, $value);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), [
                'quantity' => $value[1],
                'description' => $item->description,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2.00', $item->fresh()->quantity);
    }

    public function test_a_labor_lines_edit_form_still_requires_a_description_and_an_assignee(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $form = $this->page($job, ['edit' => $item->getKey()])->getContent();

        $this->assertStringContainsString('required', $this->control($form, 'description'));
        $this->assertStringContainsString('required', $this->control($form, 'user_id'));
    }

    private function control(string $html, string $name): string
    {
        preg_match('/<(?:input|select)\b[^>]*name="'.preg_quote($name, '/').'"[^>]*>/', $html, $match);

        $this->assertNotEmpty($match, "No control named {$name} was rendered.");

        return $match[0];
    }

    public function test_the_rendered_edit_form_keeps_fractional_hours_for_a_labor_line(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['quantity' => 1.5]);

        $input = $this->quantityInput($this->page($job, ['edit' => $item->getKey()])->getContent());

        $this->assertStringContainsString('value="1.50"', $input);
        $this->assertStringContainsString('step="0.01"', $input);
    }

    private function quantityInput(string $html): string
    {
        preg_match('/<input\b[^>]*name="quantity"[^>]*>/', $html, $match);

        $this->assertNotEmpty($match, 'No quantity input was rendered.');

        return $match[0];
    }

    public function test_an_unassigned_part_line_renders_the_empty_value_marker(): void
    {
        $item = $this->partLine();

        $this->assertNull($item->user_id);

        $this->page($item->job)
            ->assertSee(__('app.value_none'))
            ->assertSee(__('app.value_none_spoken'));
    }

    public function test_a_locked_job_offers_neither_add_link_nor_either_form(): void
    {
        $item = $this->partLine();
        $job = $item->job;
        $job->locked_at = now();
        $job->save();

        $this->page($job, ['add' => 'part'])
            ->assertDontSee(__('jobs.items.add.part_link'))
            ->assertDontSee(__('jobs.items.add.labor_link'))
            ->assertDontSee('action="'.route('jobs.parts.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.items.store', $job).'"', false)
            ->assertViewHas('partOptions', []);
    }

    public function test_the_part_picker_is_not_loaded_unless_the_part_form_renders(): void
    {
        $job = Job::factory()->inProgress()->create();
        $this->stockedPart();

        $this->page($job)->assertViewHas('partOptions', []);
        $this->page($job, ['add' => 'part'])->assertViewHas(
            'partOptions',
            fn (array $options): bool => count($options) === 1,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(Job $job, array $query = [], string $role = 'receptionist'): TestResponse
    {
        return $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', ['job' => $job, ...$query]))
            ->assertOk();
    }

    private function partLine(int $quantity = 2): JobItem
    {
        $item = app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(),
            User::factory()->receptionist()->create(),
            ['quantity' => $quantity],
        );

        return $item->load('job');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function stockedPart(array $attributes = []): Part
    {
        $part = Part::factory()->create($attributes);

        app(StockService::class)->record(
            $part,
            10,
            StockMovementReason::Restock,
            User::factory()->receptionist()->create(),
        );

        return $part;
    }
}
