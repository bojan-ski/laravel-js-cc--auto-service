<?php

namespace Tests\Feature\JobItems;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class JobItemVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_mechanics_query_does_not_select_the_price_column(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $sql = JobItem::query()->visibleTo($mechanic)->toSql();

        $this->assertStringNotContainsString('unit_price', $sql);
        $this->assertStringContainsString('`description`', $sql);
        $this->assertStringContainsString('`quantity`', $sql);
    }

    public function test_a_front_office_query_does_select_the_price_column(): void
    {
        foreach (['receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();
            $sql = JobItem::query()->visibleTo($user)->toSql();

            $this->assertStringContainsString(
                'select * from',
                $sql,
                "{$role} must receive an unrestricted select, which is what carries unit_price",
            );

            $this->assertStringNotContainsString('select `id`', $sql);
        }
    }

    public function test_reading_the_price_of_a_mechanic_scoped_item_throws(): void
    {
        $item = JobItem::factory()->create();
        $mechanic = User::factory()->mechanic()->create();

        $scoped = JobItem::query()->visibleTo($mechanic)->findOrFail($item->getKey());

        $this->assertSame($item->description, $scoped->description);

        $this->expectException(MissingAttributeException::class);

        $scoped->unit_price;
    }

    public function test_a_mechanic_scoped_item_serializes_without_the_price(): void
    {
        $item = JobItem::factory()->create();
        $mechanic = User::factory()->mechanic()->create();

        $scoped = JobItem::query()->visibleTo($mechanic)->findOrFail($item->getKey());

        $this->assertArrayNotHasKey('unit_price', $scoped->toArray());
        $this->assertArrayHasKey('quantity', $scoped->toArray());
    }

    public function test_a_front_office_scoped_item_keeps_its_price(): void
    {
        $item = JobItem::factory()->create(['unit_price' => 3000]);
        $receptionist = User::factory()->receptionist()->create();

        $scoped = JobItem::query()->visibleTo($receptionist)->findOrFail($item->getKey());

        $this->assertSame('3000.00', $scoped->unit_price);
        $this->assertArrayHasKey('unit_price', $scoped->toArray());
    }

    public function test_the_job_page_shows_no_price_line_total_or_subtotal_to_a_mechanic(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1.5, 'unit_price' => 3000]);
        JobItem::factory()->forJob($job)->create(['quantity' => 0.5, 'unit_price' => 3000]);

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $response->assertDontSee('3,000.00');
        $response->assertDontSee('6,000.00');
        $response->assertDontSee(__('jobs.items.subtotal'));
        $response->assertDontSee(__('jobs.items.headers.price'));
        $response->assertViewHas('subtotal', null);
        $response->assertViewHas('showPricing', false);
    }

    public function test_the_job_page_shows_prices_and_the_subtotal_to_front_office(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1.5, 'unit_price' => 3000]);
        JobItem::factory()->forJob($job)->create(['quantity' => 0.5, 'unit_price' => 3000]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('3,000.00')
            ->assertSee('6,000.00')
            ->assertSee(__('jobs.items.subtotal'))
            ->assertViewHas('subtotal', '6000.00');
    }

    public function test_view_pricing_is_denied_to_a_mechanic_and_allowed_to_front_office(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->mechanic()->create())->allows('viewPricing', Job::class));
        $this->assertTrue(Gate::forUser(User::factory()->receptionist()->create())->allows('viewPricing', Job::class));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewPricing', Job::class));
    }

    public function test_the_board_renders_no_monetary_value_for_any_role(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1.5, 'unit_price' => 3000]);

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('jobs.index'))
                ->assertOk()
                ->assertDontSee('3,000.00')
                ->assertDontSee('4,500.00');
        }
    }
}
