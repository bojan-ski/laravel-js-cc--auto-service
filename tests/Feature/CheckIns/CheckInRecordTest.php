<?php

namespace Tests\Feature\CheckIns;

use App\Enums\FuelLevel;
use App\Exceptions\CheckInNotOpen;
use App\Models\Job;
use App\Models\JobCheckIn;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInRecordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_mechanic_records_a_new_sheet_and_becomes_its_recorder(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->received()->create();

        $this->actingAs($mechanic)
            ->put(route('jobs.check-in.update', $job), [
                'fuel_level' => 'quarter',
                'damage_notes' => "Scratch on rear bumper\nChipped windscreen",
                'valuables' => 'Child seat',
            ])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('success', __('check_ins.flash.saved'));

        $checkIn = JobCheckIn::query()->sole();

        $this->assertSame($job->getKey(), $checkIn->job_id);
        $this->assertSame($mechanic->getKey(), $checkIn->recorded_by);
        $this->assertSame(FuelLevel::Quarter, $checkIn->fuel_level);
        $this->assertSame("Scratch on rear bumper\nChipped windscreen", $checkIn->damage_notes);
        $this->assertSame('Child seat', $checkIn->valuables);
        $this->assertNull($checkIn->signed_at);
    }

    public function test_a_receptionist_edits_it_and_the_recorder_does_not_change(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->recordedBy($mechanic)->create(['fuel_level' => FuelLevel::Empty]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('jobs.check-in.update', $job), ['fuel_level' => 'full', 'damage_notes' => ''])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $checkIn = JobCheckIn::query()->sole();

        $this->assertSame(1, JobCheckIn::query()->count());
        $this->assertSame(FuelLevel::Full, $checkIn->fuel_level);
        $this->assertNull($checkIn->damage_notes);
        $this->assertSame($mechanic->getKey(), $checkIn->recorded_by);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'fuel level missing' => [['damage_notes' => 'x'], 'fuel_level'],
            'fuel level unknown' => [['fuel_level' => 'eighth'], 'fuel_level'],
            'damage over 2000' => [['fuel_level' => 'half', 'damage_notes' => str_repeat('a', 2001)], 'damage_notes'],
            'valuables over 1000' => [['fuel_level' => 'half', 'valuables' => str_repeat('a', 1001)], 'valuables'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_an_invalid_sheet_is_refused_and_nothing_is_written(array $payload, string $field): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.check-in.show', $job))
            ->put(route('jobs.check-in.update', $job), $payload)
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, JobCheckIn::query()->count());
    }

    public function test_the_limits_themselves_are_accepted(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->put(route('jobs.check-in.update', $job), [
                'fuel_level' => 'half',
                'damage_notes' => str_repeat('a', 2000),
                'valuables' => str_repeat('a', 1000),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, JobCheckIn::query()->count());
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function closedStates(): array
    {
        $rows = [];

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $rows["{$role}, signed sheet"] = [$role, false, 'signed'];
            $rows["{$role}, completed"] = [$role, false, 'completed'];
            $rows["{$role}, cancelled"] = [$role, false, 'cancelled'];
            $rows["{$role}, locked"] = [$role, true, 'completed'];
            $rows["{$role}, locked with no sheet"] = [$role, true, 'none'];
        }

        return $rows;
    }

    #[DataProvider('closedStates')]
    public function test_saving_a_closed_sheet_is_forbidden_for_every_role(string $role, bool $locked, string $state): void
    {
        $job = match ($state) {
            'signed' => Job::factory()->inProgress()->create(),
            'none' => Job::factory()->completed()->create(),
            default => Job::factory()->{$state}()->create(),
        };

        if ($locked) {
            $job->forceFill(['locked_at' => now()])->save();
        }

        if ($state === 'signed') {
            JobCheckIn::factory()->forJob($job)->signedOnPaper()->create(['fuel_level' => FuelLevel::Empty]);
        } elseif ($state !== 'none') {
            JobCheckIn::factory()->forJob($job)->create(['fuel_level' => FuelLevel::Empty]);
        }

        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('jobs.check-in.update', $job), ['fuel_level' => 'full'])
            ->assertForbidden();

        $state === 'none'
            ? $this->assertSame(0, JobCheckIn::query()->count())
            : $this->assertSame(FuelLevel::Empty, JobCheckIn::query()->sole()->fuel_level);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function everyRoute(): array
    {
        return [
            'show' => ['get', 'jobs.check-in.show'],
            'update' => ['put', 'jobs.check-in.update'],
            'print' => ['get', 'jobs.check-in.print'],
            'photos' => ['post', 'jobs.check-in.photos.store'],
            'sign' => ['patch', 'jobs.check-in.sign'],
            'signature' => ['get', 'jobs.check-in.signature'],
        ];
    }

    #[DataProvider('everyRoute')]
    public function test_a_guest_is_sent_to_login_from_every_route(string $method, string $route): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->{$method}(route($route, $job))->assertRedirect(route('login'));
    }

    /**
     * C1: a state the policy saw as open but that closed before the job-row
     * lock was taken. The policy passes, the service refuses, and the user is
     * returned to the sheet with a flash — never a 500. The race itself cannot
     * be staged on one connection, so the service is stubbed to lose it.
     */
    public function test_a_lost_race_redirects_to_the_sheet_with_a_flash_and_changes_nothing(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create(['fuel_level' => FuelLevel::Empty]);

        $service = Mockery::mock(CheckInService::class);
        $service->shouldReceive('record')->andThrow(new CheckInNotOpen($job));
        $this->app->instance(CheckInService::class, $service);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('jobs.check-in.update', $job), ['fuel_level' => 'full'])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('error', __('check_ins.flash.not_open'));

        $this->assertSame(FuelLevel::Empty, JobCheckIn::query()->sole()->fuel_level);
    }

    public function test_the_service_re_checks_the_state_under_the_lock(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->expectException(CheckInNotOpen::class);

        app(CheckInService::class)->record($job, User::factory()->mechanic()->create(), ['fuel_level' => 'full']);
    }

    public function test_a_missing_job_is_not_found(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', ['job' => 999_999]))
            ->assertNotFound();
    }
}
