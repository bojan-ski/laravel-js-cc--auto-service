<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentTransitionRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: AppointmentStatus, 2: string}>
     */
    public static function transitionProvider(): array
    {
        return [
            'no-show' => ['appointments.no-show', AppointmentStatus::NoShow, 'no_show_at'],
            'cancel' => ['appointments.cancel', AppointmentStatus::Cancelled, 'cancelled_at'],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function test_a_transition_sets_status_and_timestamp(string $route, AppointmentStatus $status, string $timestamp): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route($route, $appointment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $appointment->refresh();

        $this->assertSame($status, $appointment->status);
        $this->assertNotNull($appointment->{$timestamp});
    }

    #[DataProvider('transitionProvider')]
    public function test_a_transition_redirects_to_the_appointments_day(string $route): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => now()->addDays(5)->setTime(9, 0),
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route($route, $appointment))
            ->assertRedirect(route('appointments.index', [
                'date' => now()->addDays(5)->format('Y-m-d'),
            ]));
    }

    /**
     * Terminal appointments are refused for every role, admin included — a
     * receptionist-only assertion would still pass if the policy collapsed to a
     * role check.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function terminalProvider(): array
    {
        $cases = [];

        foreach (['arrived', 'noShow', 'cancelled'] as $state) {
            foreach (['appointments.no-show', 'appointments.cancel'] as $route) {
                $cases["{$route} on a {$state} appointment"] = [$state, $route];
            }
        }

        return $cases;
    }

    #[DataProvider('terminalProvider')]
    public function test_an_admin_is_forbidden_from_transitioning_a_terminal_appointment(string $state, string $route): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route($route, $appointment))
            ->assertForbidden();
    }

    public function test_arrival_is_no_longer_a_one_click_transition(): void
    {
        $this->assertFalse(Route::has('appointments.arrive'));

        $this->actingAs(User::factory()->admin()->create())
            ->patch('/appointments/'.Appointment::factory()->create()->getKey().'/arrive')
            ->assertNotFound();
    }

    public function test_a_terminal_appointment_cannot_be_edited(): void
    {
        $appointment = Appointment::factory()->cancelled()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('appointments.edit', $appointment))
            ->assertForbidden();
    }
}
