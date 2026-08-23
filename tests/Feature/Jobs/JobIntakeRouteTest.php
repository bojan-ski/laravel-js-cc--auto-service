<?php

namespace Tests\Feature\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\JobStatus;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobIntakeRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_both_routes_sit_in_the_auth_band(): void
    {
        foreach (['jobs.create', 'jobs.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "{$name} is not registered");
            $this->assertSame(['web', 'auth'], $route->middleware());
        }

        $this->assertFalse(Route::has('appointments.arrive'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('jobs.create'))->assertRedirect(route('login'));
        $this->get(route('jobs.create', ['appointment' => 1]))->assertRedirect(route('login'));
        $this->post(route('jobs.store'), ['complaint' => 'x'])->assertRedirect(route('login'));
    }

    public function test_a_mechanic_is_forbidden_from_the_walk_in_path(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($mechanic)->get(route('jobs.create'))->assertForbidden();

        $this->actingAs($mechanic)
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Walk-in'])
            ->assertForbidden();

        $this->assertSame(0, Job::query()->count());
    }

    #[DataProvider('everyRoleProvider')]
    public function test_every_role_may_open_and_submit_the_appointment_path(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $appointment = Appointment::factory()->create(['scheduled_at' => now()->addDay()->setTime(10, 30)]);

        $this->actingAs($user)
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'Booked car'])
            ->assertRedirect(route('appointments.index', ['date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $job = Job::query()->sole();

        $this->assertSame($appointment->getKey(), $job->appointment_id);
        $this->assertSame($user->getKey(), $job->created_by);
        $this->assertSame(AppointmentStatus::Arrived, $appointment->fresh()->status);
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_open_and_submit_a_walk_in(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)->get(route('jobs.create'))->assertOk();

        $this->actingAs($user)
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Walk-in', 'mileage_in' => 500_000])
            ->assertRedirect(route('vehicles.show', $vehicle))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $job = Job::query()->sole();

        $this->assertNull($job->appointment_id);
        $this->assertSame($vehicle->getKey(), $job->vehicle_id);
        $this->assertSame($vehicle->customer_id, $job->customer_id);
        $this->assertSame(JobStatus::Received, $job->status);
        $this->assertSame(500_000, $vehicle->fresh()->mileage);
    }

    public function test_the_success_flash_names_the_new_reference(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Walk-in'])
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, Job::query()->sole()->reference));
    }

    public function test_the_walk_in_redirect_survives_a_soft_deleted_owner(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->customer()->first()->delete();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Walk-in']);

        $response->assertRedirect(route('vehicles.show', $vehicle));

        $this->followRedirects($response)->assertOk();
    }

    public function test_a_lower_mileage_reading_flashes_a_warning_alongside_success(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 120_000]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Walk-in', 'mileage_in' => 119_000])
            ->assertRedirect(route('vehicles.show', $vehicle))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', __('jobs.flash.mileage_lower', ['entered' => '119,000', 'stored' => '120,000']));

        $this->assertSame(120_000, $vehicle->fresh()->mileage);
        $this->assertSame(119_000, Job::query()->sole()->mileage_in);
    }

    public function test_an_unknown_appointment_on_the_form_is_a_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.create', ['appointment' => 999_999]))
            ->assertNotFound();
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
    public function test_a_terminal_appointment_refuses_to_open_the_form_even_for_an_admin(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertForbidden();
    }

    #[DataProvider('terminalProvider')]
    public function test_a_terminal_appointment_cannot_be_submitted(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'Late'])
            ->assertSessionHasErrors(['appointment_id' => __('jobs.validation.appointment_not_booked')]);

        $this->assertSame(0, Job::query()->count());
        $this->assertFalse(User::factory()->admin()->create()->can('markArrived', $appointment));
    }

    public function test_an_appointment_cannot_be_arrived_twice_over_http(): void
    {
        $appointment = Appointment::factory()->create();
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'First'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'Second'])
            ->assertSessionHasErrors('appointment_id');

        $this->assertSame(1, Job::query()->count());
    }

    public function test_the_appointment_path_does_not_consult_job_create(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $appointment = Appointment::factory()->create();

        $this->assertFalse($mechanic->can('create', Job::class));

        $this->actingAs($mechanic)
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => 'Booked car'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Job::query()->count());
    }
}
