<?php

namespace Tests\Feature\JobTemplates;

use App\Enums\JobItemType;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobTemplateLinesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_labor_line_and_a_part_line_are_stored_in_order_without_a_price(): void
    {
        $template = JobTemplate::factory()->create();
        $part = Part::factory()->create(['name' => 'Oil filter']);

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Oil service', 'quantity' => '1.25'])
            ->assertRedirect(route('job-templates.edit', $template))
            ->assertSessionHas('success', __('templates.flash.line_added'));

        $this->addLine($template, ['type' => 'part', 'part_id' => $part->getKey(), 'quantity' => '2'])
            ->assertSessionHasNoErrors();

        $lines = $template->items()->orderBy('position')->get();

        $this->assertSame(
            [
                ['labor', null, 'Oil service', '1.25', 1],
                ['part', $part->getKey(), null, '2.00', 2],
            ],
            $lines->map(fn (JobTemplateItem $line): array => [
                $line->type->value, $line->part_id, $line->description, $line->quantity, $line->position,
            ])->all(),
        );

        $columns = Schema::getColumnListing('job_template_items');
        $this->assertNotContains('unit_price', $columns);
        $this->assertNotContains('user_id', $columns);
        $this->assertNotContains('discount_percent', $columns);
    }

    public function test_a_part_line_quantity_must_be_a_positive_whole_number(): void
    {
        $template = JobTemplate::factory()->create();
        $part = Part::factory()->create();

        foreach (['1.5', '0'] as $quantity) {
            $this->addLine($template, ['type' => 'part', 'part_id' => $part->getKey(), 'quantity' => $quantity])
                ->assertSessionHasErrors('quantity');
        }

        $this->assertSame(0, $template->items()->count());
    }

    public function test_a_labor_line_takes_two_decimals_and_refuses_zero(): void
    {
        $template = JobTemplate::factory()->create();

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Zero', 'quantity' => '0'])
            ->assertSessionHasErrors('quantity');

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Quarter', 'quantity' => '1.25'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Quarter'], $template->items()->pluck('description')->all());
    }

    public function test_each_line_type_refuses_the_other_types_field(): void
    {
        $template = JobTemplate::factory()->create();
        $part = Part::factory()->create();

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Oil service', 'part_id' => $part->getKey(), 'quantity' => '1'])
            ->assertSessionHasErrors('part_id');

        $this->addLine($template, ['type' => 'part', 'part_id' => $part->getKey(), 'line_description' => 'Named', 'quantity' => '1'])
            ->assertSessionHasErrors('line_description');

        $this->assertSame(0, $template->items()->count());
    }

    public function test_a_part_line_refuses_a_trashed_or_unknown_part_and_an_unknown_type(): void
    {
        $template = JobTemplate::factory()->create();
        $trashed = Part::factory()->create();
        $trashed->delete();

        $this->addLine($template, ['type' => 'part', 'part_id' => $trashed->getKey(), 'quantity' => '1'])
            ->assertSessionHasErrors('part_id');

        $this->addLine($template, ['type' => 'part', 'part_id' => 999999, 'quantity' => '1'])
            ->assertSessionHasErrors('part_id');

        $this->addLine($template, ['type' => 'fee', 'line_description' => 'x', 'quantity' => '1'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $template->items()->count());
    }

    public function test_updating_a_line_never_changes_its_type_or_position(): void
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->create(['position' => 1]);
        $labor = JobTemplateItem::factory()->forTemplate($template)->create(['position' => 2, 'description' => 'Old']);

        $this->updateLine($template, $labor, ['type' => 'part', 'line_description' => 'New', 'quantity' => '0.75', 'position' => 1])
            ->assertRedirect(route('job-templates.edit', $template))
            ->assertSessionHas('success', __('templates.flash.line_updated'));

        $labor->refresh();
        $this->assertSame(JobItemType::Labor, $labor->type);
        $this->assertSame(2, $labor->position);
        $this->assertSame('New', $labor->description);
        $this->assertSame('0.75', $labor->quantity);
    }

    public function test_a_part_line_is_validated_as_a_part_line_whatever_type_is_submitted(): void
    {
        $template = JobTemplate::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->forPart(Part::factory()->create(), 2)->create();
        $replacement = Part::factory()->create();

        $this->updateLine($template, $line, ['type' => 'labor', 'line_description' => 'Sneaky', 'quantity' => '1.5'])
            ->assertSessionHasErrors(['line_description', 'part_id', 'quantity']);

        $this->updateLine($template, $line, ['part_id' => $replacement->getKey(), 'quantity' => '3'])
            ->assertSessionHasNoErrors();

        $line->refresh();
        $this->assertSame(JobItemType::Part, $line->type);
        $this->assertSame($replacement->getKey(), $line->part_id);
        $this->assertNull($line->description);
        $this->assertSame('3.00', $line->quantity);
    }

    public function test_saving_a_line_with_its_deleted_part_is_refused(): void
    {
        $template = JobTemplate::factory()->create();
        $part = Part::factory()->create();
        $line = JobTemplateItem::factory()->forTemplate($template)->forPart($part, 1)->create();
        $part->delete();

        $this->updateLine($template, $line, ['part_id' => $part->getKey(), 'quantity' => '2'])
            ->assertSessionHasErrors('part_id');

        $this->assertSame('1.00', $line->fresh()->quantity);
    }

    public function test_removing_a_line_hard_deletes_it_and_a_new_line_goes_after_the_highest(): void
    {
        $template = JobTemplate::factory()->create();
        $first = JobTemplateItem::factory()->forTemplate($template)->create(['position' => 1, 'description' => 'First']);
        $second = JobTemplateItem::factory()->forTemplate($template)->create(['position' => 2, 'description' => 'Second']);
        JobTemplateItem::factory()->forTemplate($template)->create(['position' => 3, 'description' => 'Third']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('job-templates.items.destroy', [$template, $second]))
            ->assertRedirect(route('job-templates.edit', $template))
            ->assertSessionHas('success', __('templates.flash.line_removed'));

        $this->assertNull(JobTemplateItem::query()->find($second->getKey()));

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Fourth', 'quantity' => '1']);

        $this->assertSame(
            ['First' => 1, 'Third' => 3, 'Fourth' => 4],
            $template->items()->orderBy('position')->pluck('position', 'description')->all(),
        );
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_the_first_line_of_a_template_takes_position_one(): void
    {
        $other = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($other)->create(['position' => 7]);
        $template = JobTemplate::factory()->create();

        $this->addLine($template, ['type' => 'labor', 'line_description' => 'Only', 'quantity' => '1']);

        $this->assertSame(1, $template->items()->sole()->position);
    }

    public function test_a_line_of_another_template_is_not_found(): void
    {
        $template = JobTemplate::factory()->create();
        $other = JobTemplate::factory()->create();
        $foreign = JobTemplateItem::factory()->forTemplate($other)->create(['description' => 'Foreign']);
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)
            ->put(route('job-templates.items.update', [$template, $foreign]), ['line_description' => 'Hijacked', 'quantity' => '1'])
            ->assertNotFound();

        $this->actingAs($receptionist)
            ->delete(route('job-templates.items.destroy', [$template, $foreign]))
            ->assertNotFound();

        $this->assertSame('Foreign', $foreign->fresh()->description);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function addLine(JobTemplate $template, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('job-templates.items.store', $template), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateLine(JobTemplate $template, JobTemplateItem $line, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-templates.items.update', [$template, $line]), $payload);
    }
}
