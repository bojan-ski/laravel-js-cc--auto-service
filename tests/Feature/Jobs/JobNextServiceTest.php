<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobNextServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'next_service_on' => '2027-04-01',
            'next_service_mileage' => '215000',
            'registration_expires_on' => '2027-02-15',
        ];
    }

    private function completedJob(): Job
    {
        return Job::factory()->completed()->create(['mileage_in' => 200000]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frontOffice(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOffice')]
    public function test_front_office_opens_the_page_with_the_jobs_context_and_the_vehicles_values(string $role): void
    {
        $job = $this->completedJob();
        $vehicle = $job->vehicle;
        $vehicle->update(['next_service_on' => '2027-01-10', 'next_service_mileage' => 210000]);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.next-service.edit', $job))
            ->assertOk()
            ->assertSee(__('vehicles.next_service.title', ['plate' => $vehicle->registration_plate]))
            ->assertSee(__('vehicles.next_service.context_mileage', [
                'reference' => $job->reference,
                'date' => $job->completed_at->isoFormat('D MMM YYYY'),
                'mileage' => '200,000',
            ]))
            ->assertSee('value="2027-01-10"', false)
            ->assertSee('value="210000"', false)
            ->assertSee(route('jobs.show', $job));
    }

    public function test_the_context_line_omits_the_mileage_when_the_job_has_none(): void
    {
        $job = Job::factory()->completed()->create(['mileage_in' => null]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.next-service.edit', $job))
            ->assertOk()
            ->assertSee(__('vehicles.next_service.context', [
                'reference' => $job->reference,
                'date' => $job->completed_at->isoFormat('D MMM YYYY'),
            ]))
            ->assertDontSee(' km', false);
    }

    public function test_cancel_returns_to_the_job(): void
    {
        $job = $this->completedJob();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.next-service.edit', $job))
            ->assertOk()
            ->assertSee('href="'.route('jobs.show', $job).'"', false)
            ->assertSeeInOrder([e(__('vehicles.next_service.submit')), route('jobs.show', $job), e(__('app.actions.cancel'))], false);
    }

    public function test_saving_writes_only_the_three_vehicle_columns_and_never_the_job(): void
    {
        $job = $this->completedJob();
        $vehicle = $job->vehicle;
        $this->travel(5)->minutes();

        $jobBefore = $job->fresh()->getAttributes();
        $vehicleBefore = $vehicle->fresh()->getAttributes();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('jobs.next-service.update', $job), $this->payload())
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('vehicles.flash.next_service_saved', ['plate' => $vehicle->registration_plate]));

        $this->assertSame($jobBefore, $job->fresh()->getAttributes());

        $changed = array_keys(array_diff_assoc($vehicle->fresh()->getAttributes(), $vehicleBefore));

        $this->assertEqualsCanonicalizing(
            ['next_service_on', 'next_service_mileage', 'registration_expires_on', 'updated_at'],
            $changed,
        );
        $this->assertSame('2027-04-01', $vehicle->fresh()->next_service_on->toDateString());
    }

    /**
     * The write goes to `vehicles`, so neither the job's nor the line items'
     * lock guard is in its path — the same reason attachments survive locking.
     */
    public function test_a_locked_job_still_accepts_it(): void
    {
        $job = Job::factory()->locked()->create();
        $actor = User::factory()->receptionist()->create();

        $this->actingAs($actor)->get(route('jobs.next-service.edit', $job))->assertOk();
        $this->actingAs($actor)->put(route('jobs.next-service.update', $job), $this->payload())->assertRedirect(route('jobs.show', $job));

        $this->assertSame('2027-04-01', $job->vehicle->fresh()->next_service_on->toDateString());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notCompleted(): array
    {
        return ['received' => ['received'], 'in progress' => ['inProgress'], 'awaiting parts' => ['awaitingParts'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('notCompleted')]
    public function test_a_job_that_is_not_completed_is_refused(string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        $actor = User::factory()->receptionist()->create();

        $this->actingAs($actor)->get(route('jobs.next-service.edit', $job))->assertForbidden();
        $this->actingAs($actor)->put(route('jobs.next-service.update', $job), $this->payload())->assertForbidden();
    }

    public function test_a_mechanic_is_refused_with_and_without_a_valid_payload(): void
    {
        $job = $this->completedJob();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('jobs.next-service.edit', $job))->assertForbidden();
        $this->actingAs($mechanic)->put(route('jobs.next-service.update', $job), $this->payload())->assertForbidden();
        $this->actingAs($mechanic)->put(route('jobs.next-service.update', $job), ['next_service_on' => 'not a date'])->assertForbidden();

        $this->assertNull($job->vehicle->fresh()->next_service_on);
    }

    public function test_a_trashed_vehicle_is_404_to_front_office_and_403_to_a_mechanic_on_both_verbs(): void
    {
        $job = $this->completedJob();
        Vehicle::query()->findOrFail($job->vehicle_id)->delete();

        $receptionist = User::factory()->receptionist()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($receptionist)->get(route('jobs.next-service.edit', $job))->assertNotFound();
        $this->actingAs($receptionist)->put(route('jobs.next-service.update', $job), $this->payload())->assertNotFound();
        $this->actingAs($mechanic)->get(route('jobs.next-service.edit', $job))->assertForbidden();
        $this->actingAs($mechanic)->put(route('jobs.next-service.update', $job), $this->payload())->assertForbidden();

        $this->assertNull(Vehicle::withTrashed()->findOrFail($job->vehicle_id)->next_service_on);
    }

    public function test_validation_errors_render_under_each_field(): void
    {
        $job = $this->completedJob();
        $actor = User::factory()->receptionist()->create();

        $this->actingAs($actor)
            ->from(route('jobs.next-service.edit', $job))
            ->put(route('jobs.next-service.update', $job), [
                'next_service_on' => 'tomorrow',
                'next_service_mileage' => '10000000',
                'registration_expires_on' => '2027-02-30',
            ])
            ->assertRedirect(route('jobs.next-service.edit', $job));

        $html = $this->actingAs($actor)->get(route('jobs.next-service.edit', $job))->getContent();

        foreach (['next_service_on', 'next_service_mileage', 'registration_expires_on'] as $field) {
            $this->assertMatchesRegularExpression('/<p id="'.$field.'-error"[^>]*>[^<]*'.preg_quote(__('vehicles.form.'.$field), '/').'/', $html, "No error names {$field}.");
        }
    }

    public function test_the_job_page_shows_the_fact_to_front_office_on_a_completed_job(): void
    {
        $job = $this->completedJob();
        $job->vehicle->update(['next_service_on' => '2027-01-10', 'next_service_mileage' => 210000]);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e(__('vehicles.next_service.label')), $html);
        $this->assertStringContainsString('10 Jan 2027 · 210,000 km', $html);
        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('jobs.next-service.edit', $job), '/').'"\s+aria-label="'.preg_quote(e(__('vehicles.next_service.set_for', ['plate' => $job->vehicle->registration_plate])), '/').'"[^>]*>'.preg_quote(e(__('vehicles.next_service.set')), '/').'<\/a>/',
            $html,
        );
    }

    public function test_the_accessible_name_contains_the_visible_link_text(): void
    {
        $this->assertStringContainsString(
            __('vehicles.next_service.set'),
            __('vehicles.next_service.set_for', ['plate' => 'BG-1']),
        );
    }

    public function test_the_fact_shows_the_empty_value_when_nothing_is_set(): void
    {
        $job = $this->completedJob();

        $html = $this->actingAs(User::factory()->receptionist()->create())->get(route('jobs.show', $job))->getContent();
        $fact = substr($html, (int) strpos($html, e(__('vehicles.next_service.label'))), 600);

        $this->assertStringContainsString(__('app.value_none_spoken'), $fact);
    }

    public function test_the_fact_is_absent_for_a_mechanic_and_on_a_working_job(): void
    {
        $label = e(__('vehicles.next_service.label'));

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $this->completedJob()))
            ->assertOk()
            ->assertDontSee($label, false)
            ->assertViewHas('showNextService', fn (mixed $value): bool => $value === false);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', Job::factory()->inProgress()->create()))
            ->assertOk()
            ->assertDontSee($label, false)
            ->assertViewHas('showNextService', fn (mixed $value): bool => $value === false);
    }

    public function test_the_fact_and_its_link_show_on_a_locked_job(): void
    {
        $job = Job::factory()->locked()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(e(__('vehicles.next_service.label')), false)
            ->assertSee('href="'.route('jobs.next-service.edit', $job).'"', false);
    }

    public function test_the_fact_has_no_link_when_the_vehicle_is_trashed(): void
    {
        $job = $this->completedJob();
        Vehicle::query()->findOrFail($job->vehicle_id)->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(e(__('vehicles.next_service.label')), false)
            ->assertDontSee(route('jobs.next-service.edit', $job));
    }
}
