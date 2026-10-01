<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ReminderKind;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Services\ReminderService;
use App\Services\VehicleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDueSoonTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00', 'Europe/Belgrade'));
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @param  array<string, mixed>  $customer
     */
    private function vehicle(array $vehicle, array $customer = []): Vehicle
    {
        return Vehicle::factory()
            ->for(Customer::factory()->create(['email' => 'o'.fake()->unique()->numberBetween(1, 99999).'@example.test', ...$customer]))
            ->create($vehicle);
    }

    private function inDays(int $days): string
    {
        return CarbonImmutable::today()->addDays($days)->toDateString();
    }

    /**
     * @return list<array{vehicle: Vehicle, kind: ReminderKind, due_on: CarbonImmutable, overdue: bool, contact: string, sent_at: ?CarbonImmutable, phone: string}>
     */
    private function rows(): array
    {
        return app(ReminderService::class)->dueSoon(CarbonImmutable::today());
    }

    private function dashboard(string $role = 'receptionist'): string
    {
        return $this->actingAs(User::factory()->{$role}()->create())->get(route('dashboard'))->assertOk()->getContent();
    }

    private function panel(string $html): string
    {
        $start = strpos($html, trans_choice('dashboard.due_soon.title', 0));
        $this->assertNotFalse($start, 'The Due soon panel did not render.');

        return substr($html, $start, (int) strpos($html, '</section>', $start) - $start);
    }

    /**
     * Tailwind's preflight removes list markers, and Safari/VoiceOver then
     * drops the list semantics and the item count unless the list says so
     * (spec 36 review, a11y-static-auditor; the check-in photo lists already do).
     */
    public function test_the_rows_are_announced_as_a_list(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)]);

        $this->assertStringContainsString('<ul role="list">', $this->panel($this->dashboard()));
    }

    public function test_it_lists_every_date_on_or_before_thirty_days_out_including_overdue(): void
    {
        $long = $this->vehicle(['next_service_on' => $this->inDays(-40)]);
        $today = $this->vehicle(['registration_expires_on' => $this->inDays(0)]);
        $edge = $this->vehicle(['next_service_on' => $this->inDays(30)]);
        $this->vehicle(['next_service_on' => $this->inDays(31)]);
        $this->vehicle(['next_service_on' => null, 'registration_expires_on' => null]);

        $this->assertEqualsCanonicalizing(
            [$long->getKey(), $today->getKey(), $edge->getKey()],
            array_map(fn (array $row): int => $row['vehicle']->getKey(), $this->rows()),
        );
        $this->assertSame(3, app(ReminderService::class)->countDueSoon(CarbonImmutable::today()));
    }

    public function test_trashed_vehicles_and_vehicles_of_trashed_owners_are_left_out(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(3)])->delete();
        $this->vehicle(['next_service_on' => $this->inDays(4)])->customer->delete();

        $this->assertSame([], $this->rows());
        $this->assertSame(0, app(ReminderService::class)->countDueSoon(CarbonImmutable::today()));
    }

    public function test_the_registration_half_honours_the_same_range_and_exclusions(): void
    {
        $edge = $this->vehicle(['registration_expires_on' => $this->inDays(30)]);
        $this->vehicle(['registration_expires_on' => $this->inDays(31)]);
        $this->vehicle(['registration_expires_on' => $this->inDays(5)])->delete();
        $this->vehicle(['registration_expires_on' => $this->inDays(6)])->customer->delete();

        $this->assertSame([$edge->getKey()], array_map(fn (array $row): int => $row['vehicle']->getKey(), $this->rows()));
        $this->assertSame(1, app(ReminderService::class)->countDueSoon(CarbonImmutable::today()));
    }

    /**
     * The reminder join must match kind and date, not just the vehicle: a
     * service reminder says nothing about the registration on the same day,
     * and a reminder for an old date says nothing about the new one.
     */
    public function test_a_reminder_only_marks_the_kind_and_date_it_was_sent_for(): void
    {
        $vehicle = $this->vehicle([
            'registration_plate' => 'KD-001',
            'next_service_on' => $this->inDays(9),
            'registration_expires_on' => $this->inDays(9),
        ]);
        VehicleReminder::factory()->forVehicle($vehicle, ReminderKind::Service)->create();

        $moved = $this->vehicle(['registration_plate' => 'MV-001', 'next_service_on' => $this->inDays(4)]);
        VehicleReminder::factory()->forVehicle($moved, ReminderKind::Service)->create();
        app(VehicleService::class)->update($moved, ['next_service_on' => $this->inDays(12)]);

        $states = collect($this->rows())->mapWithKeys(fn (array $row): array => [
            $row['vehicle']->registration_plate.' '.$row['kind']->value => [$row['contact'], $row['sent_at']],
        ])->all();

        $this->assertSame('emailed', $states['KD-001 service'][0]);
        $this->assertSame(['pending', null], $states['KD-001 registration']);
        $this->assertSame(['pending', null], $states['MV-001 service']);
        $this->assertCount(3, $states);
    }

    public function test_a_date_due_today_is_pending_and_not_overdue(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(0)]);

        $row = $this->rows()[0];

        $this->assertSame('pending', $row['contact']);
        $this->assertFalse($row['overdue']);
        $this->assertStringNotContainsString(e(__('dashboard.due_soon.overdue')), $this->panel($this->dashboard()));
    }

    /**
     * Half past midnight in Belgrade is the previous day in UTC. The dashboard
     * takes its own "today", so this pins it end to end.
     */
    public function test_just_after_midnight_the_dashboard_counts_today_in_the_application_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:30', 'Europe/Belgrade'));

        $this->vehicle(['next_service_on' => '2026-10-01']);
        $this->vehicle(['next_service_on' => '2026-10-31']);
        $this->vehicle(['next_service_on' => '2026-11-01']);

        $html = $this->dashboard();

        $this->assertStringContainsString(e(trans_choice('dashboard.due_soon.title', 2, ['count' => 2])), $html);
        $this->assertStringNotContainsString(e(__('dashboard.due_soon.overdue')), $this->panel($html));
    }

    public function test_the_contact_column_shows_the_phone_only_on_call_rows(): void
    {
        $emailed = $this->vehicle(['next_service_on' => $this->inDays(5)], ['phone' => '061 000 0001']);
        VehicleReminder::factory()->forVehicle($emailed)->create();
        $this->vehicle(['next_service_on' => $this->inDays(20)], ['phone' => '061 000 0002']);
        $this->vehicle(['next_service_on' => $this->inDays(6)], ['email' => null, 'phone' => '061 000 0003']);

        $panel = $this->panel($this->dashboard());

        $this->assertStringNotContainsString('061 000 0001', $panel);
        $this->assertStringNotContainsString('061 000 0002', $panel);
        $this->assertStringContainsString('061 000 0003', $panel);
    }

    public function test_a_vehicle_with_both_dates_in_range_appears_twice(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(3), 'registration_expires_on' => $this->inDays(8)]);

        $this->assertSame([ReminderKind::Service, ReminderKind::Registration], array_column($this->rows(), 'kind'));
    }

    public function test_rows_are_ordered_by_date_then_plate_then_kind(): void
    {
        $this->vehicle(['registration_plate' => 'ZZ-100', 'next_service_on' => $this->inDays(2)]);
        $this->vehicle(['registration_plate' => 'AA-100', 'next_service_on' => $this->inDays(2), 'registration_expires_on' => $this->inDays(2)]);
        $this->vehicle(['registration_plate' => 'MM-100', 'next_service_on' => $this->inDays(-1)]);

        $this->assertSame(
            ['MM-100 service', 'AA-100 registration', 'AA-100 service', 'ZZ-100 service'],
            array_map(fn (array $row): string => $row['vehicle']->registration_plate.' '.$row['kind']->value, $this->rows()),
        );
    }

    public function test_twenty_rows_show_fifteen_a_true_count_and_how_many_more(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->vehicle(['next_service_on' => $this->inDays($i)]);
        }

        $this->assertCount(15, $this->rows());

        $html = $this->dashboard();
        $panel = $this->panel($html);

        $this->assertStringContainsString(e(trans_choice('dashboard.due_soon.title', 20, ['count' => 20])), $html);
        $this->assertSame(15, substr_count($panel, '<li '));
        $this->assertStringContainsString(e(trans_choice('dashboard.due_soon.more', 5, ['count' => 5])), $panel);
    }

    public function test_no_more_line_when_every_row_is_shown(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(4)]);

        $this->assertStringNotContainsString(trans_choice('dashboard.due_soon.more', 1, ['count' => 1]), $this->panel($this->dashboard()));
    }

    public function test_the_contact_state_follows_the_reminder_and_the_owner(): void
    {
        $emailed = $this->vehicle(['registration_plate' => 'EM-001', 'next_service_on' => $this->inDays(5)]);
        VehicleReminder::factory()->forVehicle($emailed)->create(['sent_at' => now()->subDays(2)]);

        $this->vehicle(['registration_plate' => 'PE-001', 'next_service_on' => $this->inDays(20)]);
        $this->vehicle(['registration_plate' => 'NO-001', 'next_service_on' => $this->inDays(6)], ['email' => null]);
        $this->vehicle(['registration_plate' => 'OP-001', 'next_service_on' => $this->inDays(7)], ['receives_reminders' => false]);

        $overdueEmailed = $this->vehicle(['registration_plate' => 'OE-001', 'next_service_on' => $this->inDays(-2)]);
        VehicleReminder::factory()->forVehicle($overdueEmailed)->create();

        $this->vehicle(['registration_plate' => 'OC-001', 'next_service_on' => $this->inDays(-3)]);

        $sold = $this->vehicle(['registration_plate' => 'SO-001', 'next_service_on' => $this->inDays(8)]);
        VehicleReminder::factory()->forVehicle($sold)->create();
        app(VehicleService::class)->reassign($sold, Customer::factory()->create(['email' => 'buyer@example.test']));

        $states = collect($this->rows())->mapWithKeys(fn (array $row): array => [$row['vehicle']->registration_plate => $row['contact']])->all();

        $this->assertSame([
            'OC-001' => 'call',
            'OE-001' => 'emailed',
            'EM-001' => 'emailed',
            'NO-001' => 'call',
            'OP-001' => 'call',
            'SO-001' => 'call',
            'PE-001' => 'pending',
        ], $states);
    }

    public function test_each_contact_state_renders_its_own_text(): void
    {
        $emailed = $this->vehicle(['next_service_on' => $this->inDays(5)]);
        VehicleReminder::factory()->forVehicle($emailed)->create(['sent_at' => CarbonImmutable::parse('2026-09-28 07:00')]);
        $this->vehicle(['next_service_on' => $this->inDays(20)]);
        $this->vehicle(['next_service_on' => $this->inDays(6)], ['email' => null, 'phone' => '063 555 0101']);

        $panel = $this->panel($this->dashboard());

        $this->assertStringContainsString(e(__('dashboard.due_soon.emailed', ['date' => '28 Sep'])), $panel);
        $this->assertMatchesRegularExpression('/<span[^>]*aria-hidden="true">&check;<\/span>/', $panel);
        $this->assertStringContainsString(e(__('dashboard.due_soon.pending')), $panel);
        $this->assertStringContainsString('063 555 0101', $panel);
        $this->assertStringNotContainsString('tel:', $panel);
    }

    public function test_overdue_is_marked_with_text_not_colour_alone(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(-3)]);
        $this->vehicle(['next_service_on' => $this->inDays(3)]);

        $panel = $this->panel($this->dashboard());

        $this->assertSame(1, substr_count($panel, '<strong class="ms-1 font-bold">'.e(__('dashboard.due_soon.overdue')).'</strong>'));
        $this->assertSame([true, false], array_column($this->rows(), 'overdue'));
    }

    public function test_every_row_is_one_link_to_its_vehicle(): void
    {
        $first = $this->vehicle(['next_service_on' => $this->inDays(2)]);
        $second = $this->vehicle(['registration_expires_on' => $this->inDays(4)]);

        $panel = $this->panel($this->dashboard());

        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/s', $panel, $items);

        $this->assertCount(2, $items[1]);

        foreach ([$first, $second] as $index => $vehicle) {
            $this->assertSame(1, preg_match_all('/<a\b/', $items[1][$index]), 'A row carries more than one link.');
            $this->assertStringContainsString('href="'.route('vehicles.show', $vehicle).'"', $items[1][$index]);
        }
    }

    public function test_an_empty_panel_states_its_emptiness(): void
    {
        $this->assertStringContainsString(e(__('dashboard.due_soon.empty')), $this->panel($this->dashboard()));
    }

    public function test_a_mechanic_gets_no_panel_and_no_reminder_query(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(2)]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs(User::factory()->mechanic()->create())->get(route('dashboard'))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $response->assertDontSee(trans_choice('dashboard.due_soon.title', 0))
            ->assertViewHas('dueSoon', fn (mixed $value): bool => $value === null)
            ->assertViewHas('dueSoonCount', fn (mixed $value): bool => $value === null);

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('`vehicle_reminders`', $query);
        }
    }

    public function test_front_office_sees_the_panel_below_low_stock(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(2)]);

        foreach (['receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSeeInOrder([__('dashboard.low_stock.view_all'), trans_choice('dashboard.due_soon.title', 1, ['count' => 1])]);
        }
    }
}
