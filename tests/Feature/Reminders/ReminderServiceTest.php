<?php

namespace Tests\Feature\Reminders;

use App\Enums\ReminderKind;
use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Services\CustomerMailer;
use App\Services\ReminderService;
use App\Services\VehicleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string TODAY = '2026-10-01';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 12:00', 'Europe/Belgrade'));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    private function send(): int
    {
        return app(ReminderService::class)->sendDue($this->today());
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @param  array<string, mixed>  $customer
     */
    private function vehicle(array $vehicle = [], array $customer = []): Vehicle
    {
        return Vehicle::factory()
            ->for(Customer::factory()->create(['email' => 'owner'.fake()->unique()->numberBetween(1, 99999).'@example.test', ...$customer]))
            ->create($vehicle);
    }

    private function inDays(int $days): string
    {
        return $this->today()->addDays($days)->toDateString();
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function windowEdges(): array
    {
        return [
            'yesterday' => [-1, false],
            'today' => [0, true],
            'today + 14' => [14, true],
            'today + 15' => [15, false],
        ];
    }

    #[DataProvider('windowEdges')]
    public function test_the_window_is_today_through_today_plus_the_lead_inclusive(int $days, bool $sent): void
    {
        $this->vehicle(['next_service_on' => $this->inDays($days)]);
        $this->vehicle(['registration_expires_on' => $this->inDays($days)]);

        $this->assertSame($sent ? 2 : 0, $this->send());
        $this->assertSame($sent ? 2 : 0, VehicleReminder::query()->count());
        Mail::assertQueuedCount($sent ? 2 : 0);
    }

    /**
     * Half past midnight in Belgrade is still the previous day in UTC. A window
     * computed in UTC would slide a day and miss today's date.
     */
    #[DataProvider('windowEdges')]
    public function test_the_window_holds_just_after_midnight_in_the_application_timezone(int $days, bool $sent): void
    {
        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 00:30', 'Europe/Belgrade'));

        $this->vehicle(['next_service_on' => $this->inDays($days)]);

        $this->assertSame($sent ? 1 : 0, $this->send());
    }

    public function test_the_lead_comes_from_config(): void
    {
        config(['shop.reminders.lead_days' => 3]);

        $this->vehicle(['next_service_on' => $this->inDays(3)]);
        $this->vehicle(['next_service_on' => $this->inDays(4)]);

        $this->assertSame(1, $this->send());
    }

    public function test_a_reminder_records_what_was_sent_and_to_whom(): void
    {
        $vehicle = $this->vehicle(['next_service_on' => $this->inDays(5)]);

        $this->send();

        $reminder = VehicleReminder::query()->sole();

        $this->assertSame($vehicle->getKey(), $reminder->vehicle_id);
        $this->assertSame($vehicle->customer_id, $reminder->customer_id);
        $this->assertSame(ReminderKind::Service, $reminder->kind);
        $this->assertSame($this->inDays(5), $reminder->due_on->toDateString());
        $this->assertTrue($reminder->sent_at->equalTo(now()));

        Mail::assertQueued(ServiceReminderMail::class, fn (ServiceReminderMail $mail): bool => $mail->hasTo($vehicle->customer->email)
            && $mail->reminder->is($reminder)
            && $mail->vehicle->is($vehicle));
    }

    public function test_a_vehicle_with_both_dates_in_the_window_gets_two_emails(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(2), 'registration_expires_on' => $this->inDays(9)]);

        $this->assertSame(2, $this->send());
        Mail::assertQueuedCount(2);
        $this->assertEqualsCanonicalizing(ReminderKind::cases(), VehicleReminder::query()->pluck('kind')->all());
    }

    /**
     * Same vehicle, same date, two kinds: only the kind keeps the service row
     * from suppressing the registration reminder.
     */
    public function test_a_service_reminder_does_not_suppress_a_registration_reminder_for_the_same_date(): void
    {
        $vehicle = $this->vehicle(['next_service_on' => $this->inDays(6)]);
        $this->assertSame(1, $this->send());

        app(VehicleService::class)->update($vehicle, ['registration_expires_on' => $this->inDays(6)]);

        $this->assertSame(1, $this->send());
        $this->assertEqualsCanonicalizing(ReminderKind::cases(), VehicleReminder::query()->pluck('kind')->all());
    }

    public function test_a_refused_send_does_not_stop_the_rest_of_the_run(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(2)], ['email' => 'refused@example.test']);
        $this->vehicle(['next_service_on' => $this->inDays(3)], ['email' => 'accepted@example.test']);

        $selective = new class extends CustomerMailer
        {
            public function send(Customer $customer, Mailable $mailable): bool
            {
                return $customer->email === 'refused@example.test' ? false : parent::send($customer, $mailable);
            }
        };

        $this->assertSame(1, (new ReminderService($selective))->sendDue($this->today()));
        $this->assertSame('accepted@example.test', VehicleReminder::query()->sole()->customer->email);
    }

    /**
     * The mail is pushed after the reminder row commits, so a failing push
     * escapes the transaction with the row already saved. That reminder is lost
     * (the spec accepts it), but it is reported and the run goes on.
     */
    public function test_a_push_that_fails_after_commit_is_reported_and_the_run_continues(): void
    {
        Exceptions::fake();

        $this->vehicle(['next_service_on' => $this->inDays(2)], ['email' => 'push-fails@example.test']);
        $this->vehicle(['next_service_on' => $this->inDays(3)], ['email' => 'accepted@example.test']);

        $failingPush = new class extends CustomerMailer
        {
            public function send(Customer $customer, Mailable $mailable): bool
            {
                if ($customer->email === 'push-fails@example.test') {
                    DB::afterCommit(fn () => throw new RuntimeException('queue insert failed'));

                    return true;
                }

                return parent::send($customer, $mailable);
            }
        };

        $this->assertSame(1, (new ReminderService($failingPush))->sendDue($this->today()));
        $this->assertSame(2, VehicleReminder::query()->count());
        Mail::assertQueuedCount(1);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'queue insert failed');
    }

    public function test_a_trashed_vehicle_is_not_reminded(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)])->delete();

        $this->assertSame(0, $this->send());
    }

    public function test_a_trashed_owner_is_not_reminded(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)])->customer->delete();

        $this->assertSame(0, $this->send());
    }

    public function test_an_owner_without_an_email_is_not_reminded(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)], ['email' => null]);
        $this->vehicle(['next_service_on' => $this->inDays(5)], ['email' => '']);

        $this->assertSame(0, $this->send());
        Mail::assertNothingQueued();
    }

    public function test_an_opted_out_owner_is_not_reminded(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)], ['receives_reminders' => false]);

        $this->assertSame(0, $this->send());
    }

    /**
     * Outcome alone cannot see a dropped "no row exists" condition: the unique
     * index would turn the duplicate into a skip. Nor can the query log — it
     * records only statements that succeeded, so the refused insert never
     * appears in it (measured: that form survived the mutation). A
     * beforeExecuting listener sees every statement that was attempted.
     */
    public function test_a_second_run_the_same_day_or_the_next_day_attempts_nothing(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)]);

        $this->assertSame(1, $this->send());

        $attempted = [];
        $recording = true;

        DB::beforeExecuting(function (string $query) use (&$attempted, &$recording): void {
            if ($recording && str_starts_with($query, 'insert into `vehicle_reminders`')) {
                $attempted[] = $query;
            }
        });

        foreach ([0, 1] as $daysLater) {
            $this->travelTo(CarbonImmutable::parse(self::TODAY.' 12:00', 'Europe/Belgrade')->addDays($daysLater));

            $this->assertSame(0, $this->send());
        }

        $recording = false;

        $this->assertSame([], $attempted, 'A run attempted to record a reminder that was already sent.');

        $this->assertSame(1, VehicleReminder::query()->count());
        Mail::assertQueuedCount(1);
    }

    public function test_a_moved_date_is_reminded_again_and_a_date_moved_back_is_not(): void
    {
        $vehicle = $this->vehicle(['next_service_on' => $this->inDays(5)]);
        $this->send();

        app(VehicleService::class)->update($vehicle, ['next_service_on' => $this->inDays(10)]);
        $this->assertSame(1, $this->send());

        app(VehicleService::class)->update($vehicle, ['next_service_on' => $this->inDays(5)]);
        $this->assertSame(0, $this->send());

        $this->assertSame(2, VehicleReminder::query()->count());
    }

    public function test_a_run_after_a_skipped_day_still_sends_what_is_in_the_window(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(13)]);
        $this->vehicle(['next_service_on' => $this->inDays(0)]);

        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 12:00', 'Europe/Belgrade')->addDay());

        $this->assertSame(1, $this->send(), 'The date 12 days out is sent; yesterday\'s date is not.');
        $this->assertSame($this->inDays(12), VehicleReminder::query()->sole()->due_on->toDateString());
    }

    public function test_a_sold_vehicle_is_not_reminded_again_and_the_row_keeps_the_old_owner(): void
    {
        $vehicle = $this->vehicle(['next_service_on' => $this->inDays(5)]);
        $seller = $vehicle->customer_id;
        $this->send();

        app(VehicleService::class)->reassign($vehicle, Customer::factory()->create());

        $this->assertSame(0, $this->send());
        $this->assertSame($seller, VehicleReminder::query()->sole()->customer_id);
    }

    public function test_an_owner_who_gains_an_email_or_opts_back_in_is_reminded_on_the_next_run(): void
    {
        $noEmail = $this->vehicle(['next_service_on' => $this->inDays(5)], ['email' => null]);
        $optedOut = $this->vehicle(['next_service_on' => $this->inDays(6)], ['receives_reminders' => false]);

        $this->assertSame(0, $this->send());

        $noEmail->customer->update(['email' => 'found@example.test']);
        $optedOut->customer->update(['receives_reminders' => true]);

        $this->assertSame(2, $this->send());
    }

    /**
     * A competing run's row is what the unique index catches. It is inserted
     * before this run starts — never from inside the per-reminder transaction,
     * whose rollback would discard it — and the candidate list is injected,
     * because the real query would filter the reminded key out.
     */
    public function test_a_competing_runs_row_is_skipped_and_the_rest_of_the_run_still_queues(): void
    {
        $raced = $this->vehicle(['next_service_on' => $this->inDays(4)]);
        $free = $this->vehicle(['next_service_on' => $this->inDays(6)]);
        $existing = VehicleReminder::factory()->forVehicle($raced)->create();

        $service = new class(app(CustomerMailer::class), [$raced, $free]) extends ReminderService
        {
            /**
             * @param  list<Vehicle>  $vehicles
             */
            public function __construct(CustomerMailer $mailer, private array $vehicles)
            {
                parent::__construct($mailer);
            }

            protected function dueCandidates(CarbonImmutable $today): array
            {
                return array_map(fn (Vehicle $vehicle): array => [
                    'vehicle' => $vehicle,
                    'customer' => $vehicle->customer,
                    'kind' => ReminderKind::Service,
                    'due_on' => $vehicle->next_service_on->toImmutable(),
                ], $this->vehicles);
            }
        };

        $this->assertSame(1, $service->sendDue($this->today()));
        $this->assertModelExists($existing);
        $this->assertSame(2, VehicleReminder::query()->count());
        Mail::assertQueuedCount(1);
        Mail::assertQueued(ServiceReminderMail::class, fn (ServiceReminderMail $mail): bool => $mail->vehicle->is($free));
    }

    public function test_no_row_remains_when_the_mail_was_not_handed_to_the_queue(): void
    {
        $this->vehicle(['next_service_on' => $this->inDays(5)]);

        $refusing = new class extends CustomerMailer
        {
            public function send(Customer $customer, Mailable $mailable): bool
            {
                return false;
            }
        };

        $this->assertSame(0, (new ReminderService($refusing))->sendDue($this->today()));
        $this->assertSame(0, VehicleReminder::query()->count());
    }
}
