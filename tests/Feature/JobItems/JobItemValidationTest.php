<?php

namespace Tests\Feature\JobItems;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobItemValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'a missing description' => [['description' => null], 'description'],
            'a blank description' => [['description' => '   '], 'description'],
            'a description over 255 characters' => [['description' => str_repeat('a', 256)], 'description'],
            'a missing quantity' => [['quantity' => null], 'quantity'],
            'a non-numeric quantity' => [['quantity' => 'ninety minutes'], 'quantity'],
            'a zero quantity' => [['quantity' => '0'], 'quantity'],
            'a negative quantity' => [['quantity' => '-1'], 'quantity'],
            'a quantity below the minimum' => [['quantity' => '0.009'], 'quantity'],
            'a quantity with three decimals' => [['quantity' => '1.555'], 'quantity'],
            'a quantity beyond the column' => [['quantity' => '1000000'], 'quantity'],
            'a missing user' => [['user_id' => null], 'user_id'],
            'a user that does not exist' => [['user_id' => 999999], 'user_id'],
            'a rate with three decimals' => [['unit_price' => '3000.005'], 'unit_price'],
            'a negative rate' => [['unit_price' => '-1'], 'unit_price'],
            'a rate beyond the column' => [['unit_price' => '100000000'], 'unit_price'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_the_add_form_rejects_bad_input(array $overrides, string $field): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, JobItem::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_the_edit_form_rejects_bad_input(array $overrides, string $field): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Untouched']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), $this->payload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame('Untouched', $item->fresh()->description);
    }

    public function test_a_soft_deleted_user_cannot_be_named_on_a_line(): void
    {
        $job = Job::factory()->inProgress()->create();
        $departed = User::factory()->mechanic()->create();
        $departed->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), $this->payload(['user_id' => $departed->getKey()]))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_the_boundary_values_are_accepted(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), $this->payload([
                'description' => str_repeat('a', 255),
                'quantity' => '0.01',
                'unit_price' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $item = JobItem::query()->sole();

        $this->assertSame('0.01', $item->quantity);
        $this->assertSame('0.00', $item->unit_price);
    }

    public function test_an_omitted_rate_is_accepted_and_stamped_from_config(): void
    {
        config(['shop.labor_rate' => 3250]);

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('3250.00', JobItem::query()->sole()->unit_price);
    }

    public function test_an_empty_rate_string_passes_the_prohibition_and_falls_back_to_config(): void
    {
        config(['shop.labor_rate' => 3100]);

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.items.store', $job), [
                'description' => 'Diagnostics',
                'quantity' => '0.5',
                'unit_price' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('3100.00', JobItem::query()->sole()->unit_price);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Brake service',
            'quantity' => '1.5',
            'user_id' => User::factory()->mechanic()->create()->getKey(),
        ], $overrides);
    }
}
