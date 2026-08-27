<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyRoleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    public function test_the_route_is_named_and_authenticated(): void
    {
        $route = Route::getRoutes()->getByName('appointments.show');

        $this->assertNotNull($route);
        $this->assertSame('appointments/{appointment}', $route->uri());
        $this->assertSame(['web', 'auth'], $route->middleware());
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $appointment = Appointment::factory()->create();

        $this->get(route('appointments.show', $appointment))->assertRedirect(route('login'));
    }

    public function test_the_literal_routes_are_not_bound_as_appointments(): void
    {
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)->get(route('appointments.create'))->assertOk();
        $this->actingAs($user)->get(route('appointments.overlaps', [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
        ]))->assertOk();
    }

    #[DataProvider('everyRoleProvider')]
    public function test_every_role_can_view_a_booking(string $role): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(10, 30),
            'notes' => 'Bring the spare key',
        ]);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertViewIs('pages.appointments.show')
            ->assertSee($appointment->vehicle->registration_plate)
            ->assertSee($appointment->customer->name)
            ->assertSee($appointment->scheduled_at->isoFormat('ddd D MMM HH:mm'))
            ->assertSee('Bring the spare key')
            ->assertSee($appointment->createdBy->name);
    }

    public function test_the_heading_is_static_text_and_carries_no_link(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(10, 30),
        ]);

        $content = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->getContent();

        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/s', $content, $matches);

        $headings = array_map(
            fn (string $heading): string => html_entity_decode(trim(strip_tags($heading))),
            $matches[2],
        );

        $this->assertSame([
            $appointment->scheduled_at->isoFormat('ddd D MMM HH:mm'),
            __('appointments.show.details'),
        ], $headings);

        $this->assertStringNotContainsString('<a', $matches[0][0]);
    }

    public function test_an_arrived_booking_links_to_the_job_it_produced(): void
    {
        $appointment = Appointment::factory()->arrived()->create();
        $job = Job::factory()->forAppointment($appointment)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertSee(__('appointments.show.job'))
            ->assertSee('href="'.route('jobs.show', $job).'"', false)
            ->assertSee($job->reference);
    }

    public function test_a_booking_without_a_job_links_to_no_job(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee('href="'.route('jobs.index').'/', false);
    }

    public function test_a_soft_deleted_vehicle_and_customer_render_marked_and_unlinked(): void
    {
        $appointment = Appointment::factory()->create();
        $vehicle = $appointment->vehicle;
        $customer = $appointment->customer;

        $vehicle->delete();
        $customer->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('appointments.show', $appointment->fresh()))
            ->assertOk()
            ->assertSee($vehicle->registration_plate)
            ->assertSee($customer->name)
            ->assertSee('('.__('app.deleted').')')
            ->assertSee('('.__('vehicles.show.owner_deleted').')')
            ->assertDontSee('href="'.route('vehicles.show', $vehicle).'"', false)
            ->assertDontSee('href="'.route('customers.show', $customer).'"', false);
    }

    public function test_a_terminal_booking_shows_its_lifecycle_timestamp(): void
    {
        $appointment = Appointment::factory()->cancelled()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertSee(__('appointments.show.cancelled'))
            ->assertSee($appointment->cancelled_at->isoFormat('D MMM HH:mm'))
            ->assertDontSee(__('appointments.show.arrived'));
    }
}
