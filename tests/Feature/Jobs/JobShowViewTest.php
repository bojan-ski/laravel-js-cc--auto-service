<?php

namespace Tests\Feature\Jobs;

use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobShowViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function showPage(Job $job, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job));
    }

    public function test_the_complaint_and_mileage_render(): void
    {
        $job = Job::factory()->create([
            'complaint' => 'Pulls right under braking',
            'mileage_in' => 184_320,
        ]);

        $this->showPage($job)
            ->assertOk()
            ->assertSee('Pulls right under braking')
            ->assertSee('184,320 km');
    }

    public function test_a_missing_mileage_renders_the_placeholder_rather_than_a_blank(): void
    {
        $job = Job::factory()->create(['mileage_in' => null]);

        $this->showPage($job)
            ->assertOk()
            ->assertSee(__('jobs.show.mileage_in'))
            ->assertSee(__('app.value_none_spoken'));
    }

    public function test_only_the_timestamps_that_are_set_are_rendered(): void
    {
        $this->freezeTime();

        $job = Job::factory()->awaitingParts()->create();

        $this->showPage($job, User::factory()->mechanic()->create())
            ->assertOk()
            ->assertSee(__('jobs.show.received'))
            ->assertSee($job->created_at->isoFormat('D MMM HH:mm'))
            ->assertSee(__('jobs.show.started'))
            ->assertSee($job->started_at->isoFormat('D MMM HH:mm'))
            ->assertSee($job->awaiting_parts_at->isoFormat('D MMM HH:mm'))
            ->assertDontSee(__('jobs.show.completed'))
            ->assertDontSee(__('jobs.show.cancelled'));
    }

    public function test_the_creator_is_named(): void
    {
        $opener = User::factory()->receptionist()->create(['name' => 'Dragana Jovanović']);
        $job = Job::factory()->create(['created_by' => $opener->getKey()]);

        $this->showPage($job)
            ->assertOk()
            ->assertSee(__('jobs.show.opened_by'))
            ->assertSee('Dragana Jovanović');
    }

    public function test_a_soft_deleted_creator_is_still_named_and_marked(): void
    {
        $opener = User::factory()->receptionist()->create(['name' => 'Former Staffer']);
        $job = Job::factory()->create(['created_by' => $opener->getKey()]);
        $opener->delete();

        $this->showPage($job)
            ->assertOk()
            ->assertSee('Former Staffer')
            ->assertSee('('.__('app.deleted').')');
    }

    public function test_a_job_from_an_appointment_links_to_its_booking(): void
    {
        $appointment = Appointment::factory()->arrived()->create([
            'scheduled_at' => now()->addDay()->setTime(10, 30),
        ]);
        $job = Job::factory()->forAppointment($appointment)->create();

        $this->showPage($job)
            ->assertOk()
            ->assertSee(__('jobs.show.origin'))
            ->assertSee('href="'.route('appointments.show', $appointment).'"', false)
            ->assertSee(__('jobs.show.origin_booking', [
                'time' => $appointment->scheduled_at->isoFormat('D MMM HH:mm'),
            ]))
            ->assertDontSee(__('jobs.show.walk_in'));
    }

    public function test_a_walk_in_names_itself_as_the_origin(): void
    {
        $job = Job::factory()->walkIn()->create();

        $this->showPage($job)
            ->assertOk()
            ->assertSee(__('jobs.show.origin'))
            ->assertSee(__('jobs.show.walk_in'));
    }

    public function test_the_plate_and_customer_link_to_their_records(): void
    {
        $job = Job::factory()->create();

        $this->showPage($job)
            ->assertOk()
            ->assertSee('href="'.route('vehicles.show', $job->vehicle).'"', false)
            ->assertSee('href="'.route('customers.show', $job->customer).'"', false);
    }

    public function test_a_soft_deleted_vehicle_and_customer_render_marked_and_unlinked(): void
    {
        $job = Job::factory()->create();
        $vehicle = $job->vehicle;
        $customer = $job->customer;

        $vehicle->delete();
        $customer->delete();

        $this->showPage($job->fresh())
            ->assertOk()
            ->assertSee($vehicle->registration_plate)
            ->assertSee($customer->name)
            ->assertSee('('.__('app.deleted').')')
            ->assertSee('('.__('vehicles.show.owner_deleted').')')
            ->assertDontSee('href="'.route('vehicles.show', $vehicle).'"', false)
            ->assertDontSee('href="'.route('customers.show', $customer).'"', false);
    }

    public function test_the_page_renders_when_the_vehicle_customer_and_creator_are_all_soft_deleted(): void
    {
        $job = Job::factory()->create();

        $job->vehicle->delete();
        $job->customer->delete();
        $job->createdBy->delete();

        $this->showPage($job->fresh())
            ->assertOk()
            ->assertSee($job->reference);
    }

    public function test_both_empty_sections_render_headings_and_no_controls(): void
    {
        $job = Job::factory()->create();

        $this->showPage($job)
            ->assertOk()
            ->assertSee(__('jobs.show.line_items'))
            ->assertSee(__('jobs.show.line_items_empty'))
            ->assertSee(__('jobs.show.attachments'))
            ->assertSee(__('jobs.show.attachments_empty'))
            ->assertDontSee('mt-4 flex justify-center', false);
    }

    public function test_every_heading_names_the_section_it_introduces(): void
    {
        $job = Job::factory()->create();

        $content = $this->showPage($job)->assertOk()->getContent();

        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/s', $content, $matches);

        $headings = array_map(
            fn (string $heading): string => html_entity_decode(trim(strip_tags($heading))),
            $matches[2],
        );

        $this->assertSame([
            $job->reference,
            __('jobs.show.details'),
            __('jobs.show.line_items'),
            __('jobs.show.attachments'),
        ], $headings);
    }

    public function test_a_locked_job_renders_the_banner_and_no_controls(): void
    {
        $job = Job::factory()->locked()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->showPage($job, $admin)
            ->assertOk()
            ->assertSee(__('jobs.show.lock.title', ['date' => $job->locked_at->isoFormat('D MMM')]))
            ->assertSee(__('jobs.show.lock.body'));

        foreach (['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'] as $verb) {
            $response->assertDontSee(route("jobs.{$verb}", $job), false);
        }
    }

    public function test_an_unlocked_job_renders_no_banner(): void
    {
        $job = Job::factory()->completed()->create();

        $this->showPage($job, User::factory()->admin()->create())
            ->assertOk()
            ->assertDontSee(__('jobs.show.lock.body'))
            ->assertSee(route('jobs.reopen', $job), false);
    }
}
