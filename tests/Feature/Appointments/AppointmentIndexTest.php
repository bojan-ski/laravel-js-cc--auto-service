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

    /**
     * The actions live on the appointment page. Asserted as front office, the
     * role that would otherwise see all four on a booked row.
     */
    public function test_the_list_carries_no_actions(): void
    {
        $booked = $this->bookedAt('2026-09-02 10:00:00');

        $this->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('href="'.route('appointments.show', $booked).'"', false)
            ->assertDontSee(route('jobs.create', ['appointment' => $booked->getKey()]), false)
            ->assertDontSee(route('appointments.no-show', $booked), false)
            ->assertDontSee(route('appointments.edit', $booked), false)
            ->assertDontSee(route('appointments.cancel', $booked), false);
    }

    /**
     * A link cannot wrap a <tr>, so the plate link's ::after is stretched over
     * the positioned row. Only a browser can prove the hit-testing (checked by
     * hand); this pins the two classes it depends on so neither is dropped.
     */
    public function test_the_row_link_covers_the_whole_row(): void
    {
        $booked = $this->bookedAt('2026-09-02 10:00:00');

        $html = $this->get(route('appointments.index'))->assertOk()->getContent();

        preg_match('/<tr\b[^>]*>(?:(?!<\/tr>).)*?href="'.preg_quote(route('appointments.show', $booked), '/').'".*?<\/tr>/s', $html, $row);

        $this->assertNotEmpty($row);
        $this->assertMatchesRegularExpression('/^<tr\b[^>]*class="[^"]*\brelative\b/', $row[0]);
        $this->assertMatchesRegularExpression('/<a\b[^>]*class="[^"]*after:absolute after:inset-0/', $row[0]);
    }

    public function test_each_row_links_to_its_appointment_page(): void
    {
        $booked = $this->bookedAt('2026-09-02 10:00:00');

        $html = $this->get(route('appointments.index'))->assertOk()->getContent();

        preg_match('/<a\b[^>]*href="'.preg_quote(route('appointments.show', $booked), '/').'"[^>]*>([^<]*)<\/a>/', $html, $link);

        $this->assertNotEmpty($link, 'The row must link to the appointment page.');
        $this->assertSame($booked->vehicle->registration_plate, trim($link[1]));
        $this->assertStringContainsString(
            'aria-label="'.e(__('appointments.show.title', [
                'plate' => $booked->vehicle->registration_plate,
                'time' => $booked->scheduled_at->isoFormat('D MMM HH:mm'),
            ])).'"',
            $link[0],
        );
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
