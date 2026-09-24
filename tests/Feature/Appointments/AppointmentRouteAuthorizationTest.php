<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentRouteAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routeProvider(): array
    {
        return [
            'index' => ['get', '/appointments'],
            'create' => ['get', '/appointments/create'],
            'store' => ['post', '/appointments'],
            'overlaps' => ['get', '/appointments/overlaps'],
            'edit' => ['get', '/appointments/1/edit'],
            'update' => ['put', '/appointments/1'],
            'no-show' => ['patch', '/appointments/1/no-show'],
            'cancel' => ['patch', '/appointments/1/cancel'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_a_guest_is_redirected_to_login(string $method, string $uri): void
    {
        Appointment::factory()->create();

        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    public function test_a_mechanic_may_view_the_index(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.index'))
            ->assertOk();
    }

    public function test_a_mechanic_is_forbidden_from_every_booking_route(): void
    {
        $appointment = Appointment::factory()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('appointments.create'))->assertForbidden();
        $this->actingAs($mechanic)->post(route('appointments.store'), $this->validBooking())->assertForbidden();
        $this->actingAs($mechanic)->get(route('appointments.edit', $appointment))->assertForbidden();
        $this->actingAs($mechanic)->put(route('appointments.update', $appointment), $this->validReschedule())->assertForbidden();
        $this->actingAs($mechanic)->patch(route('appointments.cancel', $appointment))->assertForbidden();
    }

    public function test_a_mechanic_is_forbidden_from_the_overlap_endpoint(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('appointments.overlaps', [
                'scheduled_at' => now()->addDay()->toDateTimeString(),
                'duration_minutes' => 60,
            ]))
            ->assertForbidden();
    }

    public function test_a_mechanic_may_not_record_a_no_show(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $appointment = Appointment::factory()->create();

        $this->actingAs($mechanic)
            ->patch(route('appointments.no-show', $appointment))
            ->assertForbidden();

        $this->assertTrue($appointment->fresh()->isBooked());
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
    public function test_front_office_may_reach_every_read_route(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $appointment = Appointment::factory()->create();

        $this->actingAs($user)->get(route('appointments.index'))->assertOk();
        $this->actingAs($user)->get(route('appointments.create'))->assertOk();
        $this->actingAs($user)->get(route('appointments.edit', $appointment))->assertOk();
        $this->actingAs($user)->get(route('appointments.overlaps', [
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'duration_minutes' => 60,
        ]))->assertOk();
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_may_perform_every_write(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->post(route('appointments.store'), $this->validBooking())->assertRedirect();
        $this->actingAs($user)->put(route('appointments.update', Appointment::factory()->create()), $this->validReschedule())->assertRedirect();
        $this->actingAs($user)->patch(route('appointments.cancel', Appointment::factory()->create()))->assertRedirect();
        $this->actingAs($user)->patch(route('appointments.no-show', Appointment::factory()->create()))->assertRedirect();
    }

    public function test_the_overlaps_route_is_not_captured_by_a_model_bound_wildcard(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('appointments.overlaps', [
                'scheduled_at' => now()->addDay()->toDateTimeString(),
                'duration_minutes' => 60,
            ]))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validBooking(): array
    {
        return [
            'vehicle_id' => Vehicle::factory()->create()->getKey(),
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validReschedule(): array
    {
        return [
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 90,
        ];
    }
}
