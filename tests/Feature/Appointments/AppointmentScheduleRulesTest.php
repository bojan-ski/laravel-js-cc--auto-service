<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\ShopSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ShopSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A new appointment starts tomorrow at the earliest, and the whole of it
 * (start through start + duration) sits inside the shop's appointment hours.
 * An edit is held to both rules only when it moves the time or the duration.
 */
class AppointmentScheduleRulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-09 12:00:00'));
        $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(string $at, int $duration = 60): array
    {
        return [
            'vehicle_id' => Vehicle::factory()->create()->getKey(),
            'scheduled_at' => $at,
            'duration_minutes' => $duration,
        ];
    }

    private function hoursMessage(string $open = '08:00', string $close = '20:00'): string
    {
        return __('appointments.validation.outside_hours', ['open' => $open, 'close' => $close]);
    }

    public function test_later_today_is_refused(): void
    {
        $this->post(route('appointments.store'), $this->booking('2026-09-09T16:00'))
            ->assertSessionHasErrors(['scheduled_at' => __('appointments.validation.not_before_tomorrow')]);

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_tomorrow_at_opening_time_is_accepted(): void
    {
        $this->post(route('appointments.store'), $this->booking('2026-09-10T08:00'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_a_start_before_opening_is_refused(): void
    {
        $this->post(route('appointments.store'), $this->booking('2026-09-10T07:59'))
            ->assertSessionHasErrors(['scheduled_at' => $this->hoursMessage()]);
    }

    public function test_an_appointment_running_past_closing_is_refused(): void
    {
        $this->post(route('appointments.store'), $this->booking('2026-09-10T19:30', 60))
            ->assertSessionHasErrors(['scheduled_at' => $this->hoursMessage()]);
    }

    public function test_an_appointment_ending_exactly_at_closing_is_accepted(): void
    {
        $this->post(route('appointments.store'), $this->booking('2026-09-10T19:00', 60))
            ->assertSessionHasNoErrors();
    }

    public function test_the_hours_an_admin_saved_replace_the_defaults(): void
    {
        app(ShopSettingService::class)->updateAppointmentHours('09:30', '17:00');

        $this->post(route('appointments.store'), $this->booking('2026-09-10T09:00'))
            ->assertSessionHasErrors(['scheduled_at' => $this->hoursMessage('09:30', '17:00')]);

        $this->post(route('appointments.store'), $this->booking('2026-09-10T16:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_an_edit_that_keeps_the_time_is_not_held_to_the_rules(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-09 07:00:00'),
            'duration_minutes' => 60,
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-09T07:00',
            'duration_minutes' => 60,
            'notes' => 'Customer called ahead.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Customer called ahead.', $appointment->fresh()->notes);
    }

    public function test_an_edit_that_moves_the_time_to_today_is_refused(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-12 10:00:00'),
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-09T15:00',
            'duration_minutes' => $appointment->duration_minutes,
        ])->assertSessionHasErrors(['scheduled_at' => __('appointments.validation.not_before_tomorrow')]);

        $this->assertTrue(CarbonImmutable::parse('2026-09-12 10:00:00')->equalTo($appointment->fresh()->scheduled_at));
    }

    public function test_an_edit_that_lengthens_past_closing_is_refused(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-12 19:00:00'),
            'duration_minutes' => 60,
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T19:00',
            'duration_minutes' => 90,
        ])->assertSessionHasErrors(['scheduled_at' => $this->hoursMessage()]);

        $this->assertSame(60, $appointment->fresh()->duration_minutes);
    }

    public function test_the_create_form_offers_tomorrow_at_opening_as_its_minimum_and_states_the_rules(): void
    {
        app(ShopSettingService::class)->updateAppointmentHours('07:30', '18:00');

        $this->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('min="2026-09-10T07:30"', false)
            ->assertSee('aria-describedby="scheduled_at-hint"', false)
            ->assertSee(__('appointments.form.scheduled_at_hint', ['open' => '07:30', 'close' => '18:00']));
    }

    public function test_the_edit_form_sets_no_minimum_so_an_unchanged_time_can_be_resubmitted(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-09 07:00:00'),
        ]);

        $html = $this->get(route('appointments.edit', $appointment))->assertOk()->getContent();

        preg_match('/<input\b[^>]*name="scheduled_at"[^>]*>/', $html, $input);

        $this->assertNotEmpty($input);
        $this->assertStringContainsString('value="2026-09-09T07:00"', $input[0]);
        $this->assertStringNotContainsString('min=', $input[0]);
    }

    public function test_the_defaults_apply_until_an_admin_saves_hours(): void
    {
        $this->assertSame(0, ShopSetting::query()->count());

        $hours = app(ShopSettingService::class)->current();

        $this->assertSame('08:00', $hours->opensAt());
        $this->assertSame('20:00', $hours->closesAt());
    }
}
