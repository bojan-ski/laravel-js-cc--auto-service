<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTodayTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function page(): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function bookAt(string $plate, CarbonImmutable $at, string $state = 'booked'): Appointment
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => $plate]);

        return Appointment::factory()
            ->for($customer)
            ->for($vehicle)
            ->{$state}()
            ->create(['scheduled_at' => $at]);
    }

    public function test_todays_appointments_render_in_time_order(): void
    {
        $today = CarbonImmutable::today();

        $this->bookAt('BG440', $today->setTime(14, 0));
        $this->bookAt('BG123', $today->setTime(8, 0));
        $this->bookAt('NS887', $today->setTime(10, 30));

        $this->page()->assertSeeInOrder(['BG123', 'NS887', 'BG440']);
    }

    /**
     * The panel's range is half-open, so both midnight boundaries are the cases
     * that matter: a `whereBetween(startOfDay, endOfDay)` would drop nothing here,
     * but the yesterday-23:59:59 and tomorrow-00:00:00 rows catch a range widened
     * by a day in either direction.
     */
    public function test_appointments_on_other_days_are_excluded(): void
    {
        $today = CarbonImmutable::today();

        $this->bookAt('BG001', $today->subDay()->setTime(23, 59, 59));
        $this->bookAt('BG002', $today->startOfDay());
        $this->bookAt('BG003', $today->setTime(23, 59, 59));
        $this->bookAt('BG004', $today->addDay()->startOfDay());

        $page = $this->page();

        $page->assertSee('BG002')->assertSee('BG003');
        $page->assertDontSee('BG001')->assertDontSee('BG004');
    }

    public function test_every_status_appears_in_todays_picture(): void
    {
        $today = CarbonImmutable::today();

        $this->bookAt('BG010', $today->setTime(8, 0));
        $this->bookAt('BG020', $today->setTime(9, 0), 'arrived');
        $this->bookAt('BG030', $today->setTime(10, 0), 'noShow');
        $this->bookAt('BG040', $today->setTime(11, 0), 'cancelled');

        $this->page()
            ->assertSee('BG010')
            ->assertSee('BG020')
            ->assertSee('BG030')
            ->assertSee('BG040');
    }

    /**
     * `dashboard.today.arrived` and `appointments.statuses.arrived` are both the
     * word "Arrived", so seeing that word proves nothing on its own: an
     * `isArrived()` that always returned false would fall through to the badge
     * branch, which prints the identical string. The check markup is what
     * distinguishes the two branches, so this test pins that as well.
     */
    public function test_an_arrived_appointment_is_marked_and_a_booked_one_is_not(): void
    {
        $this->bookAt('BG020', CarbonImmutable::today()->setTime(9, 0), 'arrived');

        $this->page()
            ->assertSee(__('dashboard.today.arrived'))
            ->assertSee('aria-hidden="true">&check;</span>', false)
            ->assertDontSee('bg-status-arrived', false);
    }

    /**
     * The negative half of the pair above. Without it, rendering the marker
     * unconditionally would still pass every other assertion in this file.
     */
    public function test_a_booked_appointment_carries_no_marker(): void
    {
        $this->bookAt('BG010', CarbonImmutable::today()->setTime(8, 0));

        $this->page()->assertDontSee(__('dashboard.today.arrived'));
    }

    /**
     * The check glyph is decoration; the word "Arrived" beside it is what carries
     * the state to assistive technology.
     */
    public function test_the_arrival_check_is_hidden_from_assistive_technology(): void
    {
        $this->bookAt('BG020', CarbonImmutable::today()->setTime(9, 0), 'arrived');

        $this->page()->assertSee('aria-hidden="true">&check;</span>', false);
    }

    public function test_a_missed_appointment_carries_its_status(): void
    {
        $this->bookAt('BG030', CarbonImmutable::today()->setTime(10, 0), 'noShow');

        $this->page()->assertSee(__('appointments.statuses.no_show'));
    }

    /**
     * Asserted WITH escaping on: the href renders `&amp;` between the two query
     * parameters, so the raw `route()` string with a bare `&` never matches.
     */
    public function test_the_panel_links_to_the_day_view_for_today(): void
    {
        $this->page()->assertSee(
            route('appointments.index', ['view' => 'day', 'date' => CarbonImmutable::today()->format('Y-m-d')]),
        );
    }

    /**
     * Documents the intent only. Measured: dropping `orderBy('id')` leaves this
     * green, because MySQL returns primary-key order here regardless. The
     * assertion that actually fails on that mutation is
     * DashboardQueryTest::test_every_panel_query_carries_a_deterministic_tiebreak.
     */
    public function test_appointments_at_the_same_time_are_ordered_by_id(): void
    {
        $at = CarbonImmutable::today()->setTime(9, 0);

        $this->bookAt('BG777', $at);
        $this->bookAt('BG888', $at);

        $this->page()->assertSeeInOrder(['BG777', 'BG888']);
    }

    public function test_the_panel_states_its_emptiness_when_nothing_is_booked(): void
    {
        $this->bookAt('BG004', CarbonImmutable::today()->addDay()->setTime(9, 0));

        $this->page()->assertSee(__('dashboard.today.empty'));
    }

    /**
     * The whole row is the link, so the anchor must hold the time and the
     * plate, not wrap the plate alone.
     */
    public function test_every_row_is_one_link_to_its_appointment(): void
    {
        $today = CarbonImmutable::today();
        $booked = $this->bookAt('BG123', $today->setTime(8, 0));
        $missed = $this->bookAt('NS887', $today->setTime(10, 30), 'noShow');

        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([[$booked, '08:00', 'BG123'], [$missed, '10:30', 'NS887']] as [$appointment, $time, $plate]) {
            preg_match(
                '/<li\b[^>]*>\s*<a\b[^>]*href="'.preg_quote(route('appointments.show', $appointment), '/').'"[^>]*>(.*?)<\/a>\s*<\/li>/s',
                $html,
                $row,
            );

            $this->assertNotEmpty($row, "{$plate} must be a whole-row link.");
            $this->assertStringContainsString($time, $row[1]);
            $this->assertStringContainsString($plate, $row[1]);
        }
    }
}
