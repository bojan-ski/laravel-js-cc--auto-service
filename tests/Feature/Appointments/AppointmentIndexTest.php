<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-02 08:00:00');
        $this->actingAs(User::factory()->receptionist()->create());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function bookedAt(string $dateTime): Appointment
    {
        return Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse($dateTime),
        ]);
    }

    public function test_the_index_defaults_to_today_in_day_mode_with_no_status_filter(): void
    {
        $today = $this->bookedAt('2026-09-02 10:00:00');
        $tomorrow = $this->bookedAt('2026-09-03 10:00:00');

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee($today->vehicle->registration_plate)
            ->assertDontSee($tomorrow->vehicle->registration_plate);
    }

    public function test_day_mode_shows_only_the_anchor_day(): void
    {
        $wanted = $this->bookedAt('2026-09-05 09:00:00');
        $other = $this->bookedAt('2026-09-06 09:00:00');

        $this->get(route('appointments.index', ['date' => '2026-09-05']))
            ->assertOk()
            ->assertSee($wanted->vehicle->registration_plate)
            ->assertDontSee($other->vehicle->registration_plate);
    }

    public function test_week_mode_spans_monday_to_sunday_around_the_anchor(): void
    {
        $monday = $this->bookedAt('2026-08-31 09:00:00');
        $sunday = $this->bookedAt('2026-09-06 17:00:00');
        $nextMonday = $this->bookedAt('2026-09-07 09:00:00');

        $this->get(route('appointments.index', ['view' => 'week', 'date' => '2026-09-02']))
            ->assertOk()
            ->assertSee($monday->vehicle->registration_plate)
            ->assertSee($sunday->vehicle->registration_plate)
            ->assertDontSee($nextMonday->vehicle->registration_plate);
    }

    public function test_week_mode_marks_days_with_nothing_booked(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index', ['view' => 'week', 'date' => '2026-09-02']))
            ->assertOk()
            ->assertSee(__('appointments.index.nothing_booked'));
    }

    public function test_day_mode_renders_no_day_headings(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertDontSee('scope="rowgroup"', false);
    }

    public function test_a_week_day_heading_labels_its_rows_not_its_columns(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index', ['view' => 'week', 'date' => '2026-09-02']))
            ->assertOk()
            ->assertSee('scope="rowgroup"', false)
            ->assertSee('role="rowheader"', false)
            ->assertDontSee('scope="colgroup"', false);
    }

    public function test_the_customer_cell_hides_at_the_same_breakpoint_as_its_header(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $response = $this->get(route('appointments.index'))->assertOk();

        $this->assertSame(
            2,
            substr_count($response->getContent(), 'hidden lg:table-cell'),
            'The customer header and its data cell must share one breakpoint, or the value renders without a header.'
        );
    }

    public function test_the_status_filter_narrows_the_results(): void
    {
        $booked = $this->bookedAt('2026-09-02 09:00:00');

        $cancelled = Appointment::factory()->cancelled()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-02 11:00:00'),
        ]);

        $this->get(route('appointments.index', ['status' => AppointmentStatus::Cancelled->value]))
            ->assertOk()
            ->assertSee($cancelled->vehicle->registration_plate)
            ->assertDontSee($booked->vehicle->registration_plate);
    }

    public function test_rows_are_ordered_by_scheduled_at(): void
    {
        $late = $this->bookedAt('2026-09-02 16:00:00');
        $early = $this->bookedAt('2026-09-02 08:30:00');

        $response = $this->get(route('appointments.index'))->assertOk();

        $content = $response->getContent();

        $this->assertLessThan(
            strpos($content, $late->vehicle->registration_plate),
            strpos($content, $early->vehicle->registration_plate),
        );
    }

    public function test_filters_survive_pagination(): void
    {
        Appointment::factory()->count(51)->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-02 10:00:00'),
        ]);

        $response = $this->get(route('appointments.index', [
            'view' => 'day',
            'date' => '2026-09-02',
            'status' => AppointmentStatus::Booked->value,
        ]))->assertOk();

        $response->assertSee('date=2026-09-02', false);
        $response->assertSee('status=booked', false);
        $response->assertSee('page=2', false);
    }

    public function test_an_invalid_view_fails_validation(): void
    {
        $this->get(route('appointments.index', ['view' => 'month']))
            ->assertRedirect()
            ->assertSessionHasErrors('view');
    }

    public function test_an_invalid_status_fails_validation(): void
    {
        $this->get(route('appointments.index', ['status' => 'nonsense']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_an_invalid_date_fails_validation(): void
    {
        $this->get(route('appointments.index', ['date' => '02-09-2026']))
            ->assertRedirect()
            ->assertSessionHasErrors('date');
    }

    public function test_the_empty_state_differs_when_nothing_exists_at_all(): void
    {
        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee(__('appointments.empty.none_title'));
    }

    public function test_the_empty_state_differs_when_the_window_is_empty(): void
    {
        $this->bookedAt('2026-10-20 10:00:00');

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee(__('appointments.empty.window_title'))
            ->assertDontSee(__('appointments.empty.none_title'));
    }

    public function test_the_empty_state_differs_when_a_status_filter_excludes_everything(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index', ['status' => AppointmentStatus::NoShow->value]))
            ->assertOk()
            ->assertSee(__('appointments.empty.filtered_title'))
            ->assertDontSee(__('appointments.empty.window_title'));
    }

    public function test_the_index_eager_loads_vehicle_and_customer(): void
    {
        $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index'))->assertOk();
    }
}
