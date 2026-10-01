<?php

namespace Tests\Feature\Reminders;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendServiceRemindersCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_reports_how_many_reminders_it_queued(): void
    {
        Mail::fake();

        foreach ([3, 8] as $days) {
            Vehicle::factory()
                ->for(Customer::factory()->create(['email' => "owner{$days}@example.test"]))
                ->create(['next_service_on' => now()->addDays($days)]);
        }

        $this->artisan('reminders:send')
            ->expectsOutput('Queued 2 reminder(s).')
            ->assertSuccessful();

        Mail::assertQueuedCount(2);
    }

    /**
     * The command takes its own "today". At half past midnight in Belgrade it
     * is still yesterday in UTC, which would move both window edges a day.
     */
    public function test_just_after_midnight_it_uses_the_application_timezones_today(): void
    {
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:30', 'Europe/Belgrade'));

        foreach (['2026-10-01' => 'today', '2026-10-16' => 'too-far', '2026-09-30' => 'past'] as $date => $tag) {
            Vehicle::factory()
                ->for(Customer::factory()->create(['email' => "{$tag}@example.test"]))
                ->create(['next_service_on' => $date]);
        }

        $this->artisan('reminders:send')
            ->expectsOutput('Queued 1 reminder(s).')
            ->assertSuccessful();

        $this->assertSame('2026-10-01', VehicleReminder::query()->sole()->due_on->toDateString());
    }

    public function test_it_succeeds_when_there_is_nothing_to_send(): void
    {
        $this->artisan('reminders:send')
            ->expectsOutput('Queued 0 reminder(s).')
            ->assertSuccessful();
    }

    public function test_it_is_scheduled_once_daily_at_seven_in_belgrade_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'reminders:send'));

        $this->assertCount(1, $events);

        $event = $events->first();

        $this->assertSame('0 7 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame('Europe/Belgrade', (string) $event->timezone);
    }
}
