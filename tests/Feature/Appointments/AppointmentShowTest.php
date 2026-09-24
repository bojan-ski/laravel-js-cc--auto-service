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
            ->assertSeeInOrder([
                $vehicle->registration_plate,
                '('.__('app.deleted').')',
                $customer->name,
                '('.__('app.deleted').')',
            ])
            ->assertDontSee('href="'.route('vehicles.show', $vehicle).'"', false)
            ->assertDontSee('href="'.route('customers.show', $customer).'"', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return [
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_gets_all_four_actions_on_a_booked_appointment(string $role): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertSee('href="'.route('jobs.create', ['appointment' => $appointment->getKey()]).'"', false)
            ->assertSee('action="'.route('appointments.no-show', $appointment).'"', false)
            ->assertSee('href="'.route('appointments.edit', $appointment).'"', false)
            ->assertSee('action="'.route('appointments.cancel', $appointment).'"', false);
    }

    public function test_the_actions_follow_the_heading_and_precede_the_details(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('appointments.show', $appointment))
            ->assertSeeInOrder([
                '<h1',
                route('jobs.create', ['appointment' => $appointment->getKey()]),
                __('appointments.show.details'),
            ], false);
    }

    public function test_a_mechanic_gets_no_actions(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee(route('jobs.create', ['appointment' => $appointment->getKey()]), false)
            ->assertDontSee(route('appointments.no-show', $appointment), false)
            ->assertDontSee(route('appointments.edit', $appointment), false)
            ->assertDontSee(route('appointments.cancel', $appointment), false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function terminalProvider(): array
    {
        return [
            'arrived' => ['arrived'],
            'no-show' => ['noShow'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function test_a_terminal_appointment_offers_no_actions_even_to_an_admin(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee(route('jobs.create', ['appointment' => $appointment->getKey()]), false)
            ->assertDontSee(route('appointments.no-show', $appointment), false)
            ->assertDontSee(route('appointments.edit', $appointment), false)
            ->assertDontSee(route('appointments.cancel', $appointment), false);
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
