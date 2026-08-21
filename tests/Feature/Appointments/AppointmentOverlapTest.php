<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentOverlapTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The candidate window every test measures against: 10:00–11:00.
     */
    private function candidateStart(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-01 10:00:00');
    }

    private function existingAt(string $time, int $durationMinutes): Appointment
    {
        return Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse("2026-09-01 {$time}"),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    private function overlapCount(?int $excludeId = null): int
    {
        return Appointment::overlapping($this->candidateStart(), 60, $excludeId)->count();
    }

    public function test_it_finds_an_appointment_whose_window_partially_precedes_the_candidate(): void
    {
        $this->existingAt('09:30:00', 60);

        $this->assertSame(1, $this->overlapCount());
    }

    public function test_it_finds_an_appointment_whose_window_partially_follows_the_candidate(): void
    {
        $this->existingAt('10:30:00', 60);

        $this->assertSame(1, $this->overlapCount());
    }

    public function test_it_finds_an_appointment_that_fully_contains_the_candidate(): void
    {
        $this->existingAt('09:00:00', 180);

        $this->assertSame(1, $this->overlapCount());
    }

    public function test_it_finds_an_appointment_fully_contained_by_the_candidate(): void
    {
        $this->existingAt('10:15:00', 15);

        $this->assertSame(1, $this->overlapCount());
    }

    public function test_it_ignores_an_appointment_ending_exactly_when_the_candidate_starts(): void
    {
        $this->existingAt('09:00:00', 60);

        $this->assertSame(0, $this->overlapCount());
    }

    public function test_it_ignores_an_appointment_starting_exactly_when_the_candidate_ends(): void
    {
        $this->existingAt('11:00:00', 60);

        $this->assertSame(0, $this->overlapCount());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function terminalStateProvider(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'no-show' => ['noShow'],
            'arrived' => ['arrived'],
        ];
    }

    #[DataProvider('terminalStateProvider')]
    public function test_it_ignores_terminal_appointments(string $state): void
    {
        Appointment::factory()->{$state}()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-01 10:15:00'),
            'duration_minutes' => 30,
        ]);

        $this->assertSame(0, $this->overlapCount());
    }

    public function test_it_excludes_the_appointment_named_by_exclude_id(): void
    {
        $appointment = $this->existingAt('10:15:00', 30);

        $this->assertSame(1, $this->overlapCount());
        $this->assertSame(0, $this->overlapCount($appointment->getKey()));
    }

    public function test_exclude_id_removes_only_the_named_appointment(): void
    {
        $excluded = $this->existingAt('10:15:00', 30);
        $this->existingAt('10:30:00', 60);

        $this->assertSame(1, $this->overlapCount($excluded->getKey()));
    }

    public function test_creating_an_appointment_succeeds_despite_an_overlap(): void
    {
        $this->existingAt('10:00:00', 60);

        $appointment = app(AppointmentService::class)->create(
            Vehicle::factory()->create(),
            User::factory()->receptionist()->create(),
            [
                'scheduled_at' => $this->candidateStart(),
                'duration_minutes' => 60,
            ],
        );

        $this->assertTrue($appointment->exists);
        $this->assertSame(2, Appointment::overlapping($this->candidateStart(), 60)->count());
    }

    public function test_rescheduling_onto_an_occupied_window_succeeds(): void
    {
        $this->existingAt('10:00:00', 60);
        $appointment = $this->existingAt('15:00:00', 60);

        app(AppointmentService::class)->reschedule($appointment, [
            'scheduled_at' => $this->candidateStart(),
            'duration_minutes' => 60,
        ]);

        $this->assertTrue($this->candidateStart()->equalTo($appointment->fresh()->scheduled_at));
    }
}
