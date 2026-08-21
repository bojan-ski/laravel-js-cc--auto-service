<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentTransitionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): AppointmentService
    {
        return app(AppointmentService::class);
    }

    public function test_a_booked_appointment_can_be_cancelled(): void
    {
        $appointment = Appointment::factory()->create();

        $this->service()->cancel($appointment);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNotNull($appointment->cancelled_at);
        $this->assertNull($appointment->arrived_at);
        $this->assertNull($appointment->no_show_at);
    }

    public function test_a_booked_appointment_can_be_marked_arrived(): void
    {
        $appointment = Appointment::factory()->create();

        $this->service()->markArrived($appointment);

        $this->assertSame(AppointmentStatus::Arrived, $appointment->status);
        $this->assertNotNull($appointment->arrived_at);
        $this->assertNull($appointment->no_show_at);
        $this->assertNull($appointment->cancelled_at);
    }

    public function test_a_booked_appointment_can_be_marked_no_show(): void
    {
        $appointment = Appointment::factory()->create();

        $this->service()->markNoShow($appointment);

        $this->assertSame(AppointmentStatus::NoShow, $appointment->status);
        $this->assertNotNull($appointment->no_show_at);
        $this->assertNull($appointment->arrived_at);
        $this->assertNull($appointment->cancelled_at);
    }

    public function test_a_transition_persists_to_the_database(): void
    {
        $appointment = Appointment::factory()->create();

        $this->service()->markArrived($appointment);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->getKey(),
            'status' => AppointmentStatus::Arrived->value,
        ]);
    }

    /**
     * Every terminal status crossed with every transition that leaves `booked`.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function terminalTransitionProvider(): array
    {
        $cases = [];

        foreach (['arrived' => 'arrived', 'noShow' => 'no-show', 'cancelled' => 'cancelled'] as $state => $label) {
            foreach (['cancel', 'markArrived', 'markNoShow'] as $transition) {
                $cases["{$transition} a {$label} appointment"] = [$state, $transition];
            }
        }

        return $cases;
    }

    /**
     * The lifecycle fields, reduced to scalars so they compare by value.
     *
     * @return array<string, string|null>
     */
    private function lifecycleSnapshot(Appointment $appointment): array
    {
        $appointment = $appointment->fresh();

        return [
            'status' => $appointment->status->value,
            'arrived_at' => $appointment->arrived_at?->toDateTimeString(),
            'no_show_at' => $appointment->no_show_at?->toDateTimeString(),
            'cancelled_at' => $appointment->cancelled_at?->toDateTimeString(),
        ];
    }

    #[DataProvider('terminalTransitionProvider')]
    public function test_a_transition_from_a_terminal_status_throws(string $state, string $transition): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->expectException(InvalidAppointmentTransition::class);

        $this->service()->{$transition}($appointment);
    }

    #[DataProvider('terminalTransitionProvider')]
    public function test_a_refused_transition_leaves_the_record_unchanged(string $state, string $transition): void
    {
        $appointment = Appointment::factory()->{$state}()->create();
        $before = $this->lifecycleSnapshot($appointment);

        try {
            $this->service()->{$transition}($appointment);
            $this->fail('Expected InvalidAppointmentTransition to be thrown.');
        } catch (InvalidAppointmentTransition) {
            // Expected — the assertion below is the point of the test.
        }

        $this->assertSame($before, $this->lifecycleSnapshot($appointment));
    }

    public function test_the_exception_carries_the_appointment_transition_and_status(): void
    {
        $appointment = Appointment::factory()->cancelled()->create();

        try {
            $this->service()->markArrived($appointment);
            $this->fail('Expected InvalidAppointmentTransition to be thrown.');
        } catch (InvalidAppointmentTransition $exception) {
            $this->assertTrue($appointment->is($exception->appointment));
            $this->assertSame('mark arrived', $exception->transition);
            $this->assertSame(AppointmentStatus::Cancelled, $exception->status);
        }
    }

    public function test_a_booked_appointment_can_be_rescheduled_without_changing_status(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => now()->addDay()->setTime(9, 0),
            'duration_minutes' => 60,
            'notes' => 'Original.',
        ]);

        $this->service()->reschedule($appointment, [
            'scheduled_at' => now()->addDays(2)->setTime(14, 30),
            'duration_minutes' => 120,
            'notes' => 'Moved.',
        ]);

        $this->assertTrue(now()->addDays(2)->setTime(14, 30)->equalTo($appointment->scheduled_at));
        $this->assertSame(120, $appointment->duration_minutes);
        $this->assertSame('Moved.', $appointment->notes);
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
    }

    public function test_rescheduling_cannot_change_the_vehicle_or_customer(): void
    {
        $appointment = Appointment::factory()->create();
        $originalVehicle = $appointment->vehicle_id;
        $originalCustomer = $appointment->customer_id;

        $otherVehicle = Vehicle::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $this->service()->reschedule($appointment, [
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'vehicle_id' => $otherVehicle->getKey(),
            'customer_id' => $otherCustomer->getKey(),
        ]);

        $this->assertSame($originalVehicle, $appointment->fresh()->vehicle_id);
        $this->assertSame($originalCustomer, $appointment->fresh()->customer_id);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function terminalStateProvider(): array
    {
        return [
            'arrived' => ['arrived'],
            'no-show' => ['noShow'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('terminalStateProvider')]
    public function test_rescheduling_a_terminal_appointment_throws(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->expectException(InvalidAppointmentTransition::class);

        $this->service()->reschedule($appointment, [
            'scheduled_at' => now()->addDays(4)->setTime(11, 0),
        ]);
    }

    #[DataProvider('terminalStateProvider')]
    public function test_a_refused_reschedule_leaves_the_schedule_unchanged(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create([
            'scheduled_at' => now()->addDay()->setTime(9, 0),
        ]);

        try {
            $this->service()->reschedule($appointment, [
                'scheduled_at' => now()->addDays(9)->setTime(17, 0),
            ]);
            $this->fail('Expected InvalidAppointmentTransition to be thrown.');
        } catch (InvalidAppointmentTransition) {
            // Expected — the assertion below is the point of the test.
        }

        $this->assertTrue(now()->addDay()->setTime(9, 0)->equalTo($appointment->fresh()->scheduled_at));
    }
}
