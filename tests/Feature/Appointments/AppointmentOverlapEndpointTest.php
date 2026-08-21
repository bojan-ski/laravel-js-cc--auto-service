<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppointmentOverlapEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->receptionist()->create());
    }

    private function bookedAt(string $time, int $duration = 60): Appointment
    {
        return Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse("2026-09-15 {$time}"),
            'duration_minutes' => $duration,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function overlapQuery(array $extra = []): TestResponse
    {
        return $this->getJson(route('appointments.overlaps', [
            'scheduled_at' => '2026-09-15 10:00:00',
            'duration_minutes' => 60,
            ...$extra,
        ]));
    }

    public function test_it_returns_overlapping_appointments_in_resource_shape(): void
    {
        $appointment = $this->bookedAt('10:30:00');

        $this->overlapQuery()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appointment->id)
            ->assertJsonPath('data.0.plate', $appointment->vehicle->registration_plate)
            ->assertJsonPath('data.0.customerName', $appointment->customer->name)
            ->assertJsonPath('data.0.durationMinutes', 60)
            ->assertJsonStructure(['data' => [['id', 'scheduledAt', 'durationMinutes', 'endsAt', 'plate', 'vehicleLabel', 'customerName']]]);
    }

    public function test_it_returns_an_empty_collection_when_nothing_overlaps(): void
    {
        $this->bookedAt('14:00:00');

        $this->overlapQuery()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_honours_the_exclude_parameter(): void
    {
        $appointment = $this->bookedAt('10:30:00');

        $this->overlapQuery()->assertJsonCount(1, 'data');
        $this->overlapQuery(['exclude' => $appointment->getKey()])->assertJsonCount(0, 'data');
    }

    public function test_it_treats_boundaries_as_half_open(): void
    {
        $this->bookedAt('09:00:00');
        $this->bookedAt('11:00:00');

        $this->overlapQuery()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_ignores_terminal_appointments(): void
    {
        Appointment::factory()->cancelled()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-15 10:30:00'),
            'duration_minutes' => 30,
        ]);

        $this->overlapQuery()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_missing_parameters_fail_validation(): void
    {
        $this->getJson(route('appointments.overlaps'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at', 'duration_minutes']);
    }

    public function test_an_unknown_exclude_id_fails_validation(): void
    {
        $this->overlapQuery(['exclude' => 99999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exclude');
    }
}
