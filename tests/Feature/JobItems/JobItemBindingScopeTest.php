<?php

namespace Tests\Feature\JobItems;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * job-items.update and job-items.destroy used to bind the model implicitly, which
 * hydrated unit_price into a mechanic's request. Nothing rendered it, but it left
 * the money boundary resting on the view rather than on the query.
 */
class JobItemBindingScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function writeRouteProvider(): array
    {
        return [
            'update' => ['update'],
            'destroy' => ['destroy'],
        ];
    }

    #[DataProvider('writeRouteProvider')]
    public function test_a_mechanics_line_item_write_never_selects_a_price(string $action): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create(['unit_price' => '3000.00']);

        DB::enableQueryLog();

        $action === 'update'
            ? $this->actingAs($mechanic)
                ->put(route('job-items.update', $item), [
                    'quantity' => '2',
                    'description' => 'Edited',
                ])
                ->assertSessionHasNoErrors()
            : $this->actingAs($mechanic)
                ->delete(route('job-items.destroy', $item))
                ->assertSessionHasNoErrors();

        $reads = array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $query): bool => str_contains($query, 'from `job_items`')
                && ! str_starts_with($query, 'select count'),
        ));

        DB::disableQueryLog();

        $this->assertNotEmpty($reads);

        foreach ($reads as $read) {
            $this->assertStringNotContainsString(
                'select *',
                $read,
                'An unscoped read hydrates unit_price for a mechanic: '.$read,
            );
            $this->assertStringNotContainsString('unit_price', $read, $read);
        }
    }

    public function test_front_office_still_reads_the_price_it_is_entitled_to(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['unit_price' => '3000.00']);

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), [
                'quantity' => '2',
                'description' => 'Edited',
                'user_id' => $item->user_id,
            ])
            ->assertSessionHasNoErrors();
        $reads = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertNotEmpty(array_filter(
            $reads,
            fn (string $query): bool => str_starts_with($query, 'select * from `job_items`'),
        ));

        $this->assertSame('3000.00', $item->fresh()->unit_price);
    }

    public function test_a_missing_line_item_still_produces_a_404(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('job-items.destroy', 999999))
            ->assertNotFound();
    }

    public function test_the_part_line_validation_branch_survives_the_unbound_route(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->part()->create(['quantity' => 2]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '1.5'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('2.00', $item->fresh()->quantity);
    }
}
