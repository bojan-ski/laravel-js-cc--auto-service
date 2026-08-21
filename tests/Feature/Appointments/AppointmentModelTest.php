<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_booked_appointment_passes_every_guard(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertTrue($appointment->isBooked());
        $this->assertFalse($appointment->isTerminal());
        $this->assertTrue($appointment->canBeCancelled());
        $this->assertTrue($appointment->canBeMarkedArrived());
        $this->assertTrue($appointment->canBeMarkedNoShow());
        $this->assertTrue($appointment->canBeRescheduled());
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
    public function test_a_terminal_appointment_fails_every_guard(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->assertFalse($appointment->isBooked());
        $this->assertTrue($appointment->isTerminal());
        $this->assertFalse($appointment->canBeCancelled());
        $this->assertFalse($appointment->canBeMarkedArrived());
        $this->assertFalse($appointment->canBeMarkedNoShow());
        $this->assertFalse($appointment->canBeRescheduled());
    }

    public function test_a_new_appointment_carries_the_initial_status(): void
    {
        $this->assertSame(AppointmentStatus::Booked, (new Appointment)->status);
    }

    public function test_ends_at_is_the_start_plus_the_duration(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-01 10:00:00'),
            'duration_minutes' => 90,
        ]);

        $this->assertTrue(
            CarbonImmutable::parse('2026-09-01 11:30:00')->equalTo($appointment->endsAt())
        );
    }

    public function test_ends_at_does_not_mutate_scheduled_at(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-01 10:00:00'),
            'duration_minutes' => 90,
        ]);

        $appointment->endsAt();

        $this->assertTrue(
            CarbonImmutable::parse('2026-09-01 10:00:00')->equalTo($appointment->scheduled_at)
        );
    }

    public function test_only_booked_is_non_terminal(): void
    {
        $this->assertFalse(AppointmentStatus::Booked->isTerminal());
        $this->assertTrue(AppointmentStatus::Arrived->isTerminal());
        $this->assertTrue(AppointmentStatus::NoShow->isTerminal());
        $this->assertTrue(AppointmentStatus::Cancelled->isTerminal());
    }

    public function test_each_status_labels_from_the_language_file(): void
    {
        $this->assertSame('Booked', AppointmentStatus::Booked->label());
        $this->assertSame('Arrived', AppointmentStatus::Arrived->label());
        $this->assertSame('No-show', AppointmentStatus::NoShow->label());
        $this->assertSame('Cancelled', AppointmentStatus::Cancelled->label());
    }

    public function test_each_status_names_its_own_theme_token(): void
    {
        $this->assertSame('status-booked', AppointmentStatus::Booked->colorToken());
        $this->assertSame('status-arrived', AppointmentStatus::Arrived->colorToken());
        $this->assertSame('status-no-show', AppointmentStatus::NoShow->colorToken());
        $this->assertSame('status-cancelled', AppointmentStatus::Cancelled->colorToken());
    }

    public function test_no_status_uses_the_brand_colour(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertStringStartsWith('status-', $status->colorToken());
        }
    }

    public function test_the_status_column_stores_the_enum_value_as_a_string(): void
    {
        $appointment = Appointment::factory()->cancelled()->create();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->getKey(),
            'status' => 'cancelled',
        ]);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_the_table_has_every_specified_column(): void
    {
        foreach ([
            'id', 'customer_id', 'vehicle_id', 'scheduled_at', 'duration_minutes',
            'status', 'arrived_at', 'no_show_at', 'cancelled_at', 'notes',
            'created_by', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('appointments', $column),
                "Expected the appointments table to have a {$column} column."
            );
        }
    }

    public function test_appointments_are_never_soft_deleted(): void
    {
        $this->assertFalse(Schema::hasColumn('appointments', 'deleted_at'));
    }

    public function test_duration_defaults_to_sixty_minutes(): void
    {
        $vehicle = Vehicle::factory()->create();

        $id = Appointment::query()->insertGetId([
            'customer_id' => $vehicle->customer_id,
            'vehicle_id' => $vehicle->getKey(),
            'scheduled_at' => now()->addDay(),
            'status' => AppointmentStatus::Booked->value,
            'created_by' => User::factory()->create()->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(60, Appointment::findOrFail($id)->duration_minutes);
    }

    public function test_deleting_a_customer_with_an_appointment_is_restricted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);

        Customer::withTrashed()->findOrFail($appointment->customer_id)->forceDelete();
    }

    public function test_deleting_a_vehicle_with_an_appointment_is_restricted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);

        Vehicle::withTrashed()->findOrFail($appointment->vehicle_id)->forceDelete();
    }

    public function test_deleting_the_booking_user_is_restricted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);

        User::withTrashed()->findOrFail($appointment->created_by)->forceDelete();
    }

    /**
     * @return array<string, array{0: string, 1: AppointmentStatus, 2: string|null}>
     */
    public static function factoryStateProvider(): array
    {
        return [
            'booked' => ['booked', AppointmentStatus::Booked, null],
            'arrived' => ['arrived', AppointmentStatus::Arrived, 'arrived_at'],
            'no-show' => ['noShow', AppointmentStatus::NoShow, 'no_show_at'],
            'cancelled' => ['cancelled', AppointmentStatus::Cancelled, 'cancelled_at'],
        ];
    }

    #[DataProvider('factoryStateProvider')]
    public function test_each_factory_state_sets_only_its_own_timestamp(string $state, AppointmentStatus $status, ?string $timestamp): void
    {
        $appointment = Appointment::factory()->{$state}()->create();

        $this->assertSame($status, $appointment->status);

        foreach (['arrived_at', 'no_show_at', 'cancelled_at'] as $column) {
            $column === $timestamp
                ? $this->assertNotNull($appointment->{$column}, "Expected {$column} to be set.")
                : $this->assertNull($appointment->{$column}, "Expected {$column} to be null.");
        }
    }

    public function test_the_customer_relation_resolves_when_the_customer_is_soft_deleted(): void
    {
        $appointment = Appointment::factory()->create();
        Customer::findOrFail($appointment->customer_id)->delete();

        $loaded = Appointment::with('customer')->findOrFail($appointment->getKey());

        $this->assertNotNull($loaded->customer);
        $this->assertSame($appointment->customer_id, $loaded->customer->getKey());
        $this->assertTrue($loaded->customer->trashed());
    }

    public function test_the_vehicle_relation_resolves_when_the_vehicle_is_soft_deleted(): void
    {
        $appointment = Appointment::factory()->create();
        Vehicle::findOrFail($appointment->vehicle_id)->delete();

        $loaded = Appointment::with('vehicle')->findOrFail($appointment->getKey());

        $this->assertNotNull($loaded->vehicle);
        $this->assertSame($appointment->vehicle_id, $loaded->vehicle->getKey());
        $this->assertTrue($loaded->vehicle->trashed());
    }

    public function test_the_creator_relation_resolves_when_the_user_is_soft_deleted(): void
    {
        $appointment = Appointment::factory()->create();
        User::findOrFail($appointment->created_by)->delete();

        $loaded = Appointment::with('createdBy')->findOrFail($appointment->getKey());

        $this->assertNotNull($loaded->createdBy);
        $this->assertSame($appointment->created_by, $loaded->createdBy->getKey());
        $this->assertTrue($loaded->createdBy->trashed());
    }

    public function test_a_customer_lists_only_its_own_appointments(): void
    {
        $appointment = Appointment::factory()->create();
        Appointment::factory()->create();

        $customer = Customer::with('appointments')->findOrFail($appointment->customer_id);

        $this->assertCount(1, $customer->appointments);
        $this->assertTrue($customer->appointments->first()->is($appointment));
    }

    public function test_a_vehicle_lists_only_its_own_appointments(): void
    {
        $appointment = Appointment::factory()->create();
        Appointment::factory()->create();

        $vehicle = Vehicle::with('appointments')->findOrFail($appointment->vehicle_id);

        $this->assertCount(1, $vehicle->appointments);
        $this->assertTrue($vehicle->appointments->first()->is($appointment));
    }

    public function test_the_factory_gives_the_appointment_the_vehicles_owner(): void
    {
        $appointment = Appointment::factory()->create();

        $vehicle = Vehicle::withTrashed()->findOrFail($appointment->vehicle_id);

        $this->assertSame($vehicle->customer_id, $appointment->customer_id);
    }
}
