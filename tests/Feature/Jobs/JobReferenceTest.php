<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobReferenceGenerator;
use App\Services\JobService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobReferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function create(): Job
    {
        return app(JobService::class)->createWalkIn(
            Vehicle::factory()->create(),
            User::factory()->receptionist()->create(),
            ['complaint' => 'Rattle over bumps'],
        );
    }

    public function test_the_reference_matches_the_format_for_the_creation_year(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $this->assertSame('JOB-2026-0001', $this->create()->reference);
    }

    public function test_the_sequence_increments_within_a_year(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $this->assertSame('JOB-2026-0001', $this->create()->reference);
        $this->assertSame('JOB-2026-0002', $this->create()->reference);
        $this->assertSame('JOB-2026-0003', $this->create()->reference);
    }

    public function test_the_sequence_restarts_each_calendar_year(): void
    {
        $this->travelTo('2026-12-31 23:59:00');
        $this->assertSame('JOB-2026-0001', $this->create()->reference);
        $this->assertSame('JOB-2026-0002', $this->create()->reference);

        $this->travelTo('2027-01-01 00:01:00');
        $this->assertSame('JOB-2027-0001', $this->create()->reference);
    }

    public function test_a_gap_in_the_sequence_is_never_back_filled(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        Job::factory()->create(['reference' => 'JOB-2026-0005']);

        $this->assertSame('JOB-2026-0006', $this->create()->reference);
    }

    public function test_the_generator_ignores_other_years_when_finding_the_last_number(): void
    {
        Job::factory()->create(['reference' => 'JOB-2025-0400']);

        $this->assertSame('JOB-2026-0001', app(JobReferenceGenerator::class)->generate(now()->setYear(2026)));
    }

    public function test_a_collision_is_recovered_by_recomputing_the_reference(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $taken = Job::factory()->create()->reference;
        $following = (new JobReferenceGenerator)->generate(now());
        $this->assertNotSame($taken, $following);

        $generator = new StaleReferenceGenerator($taken, staleCalls: 1);
        $this->app->instance(JobReferenceGenerator::class, $generator);

        $job = $this->create();

        $this->assertSame($following, $job->reference);
        $this->assertSame(2, $generator->calls);
        $this->assertSame(2, Job::query()->count());
    }

    public function test_the_retry_gives_up_after_three_attempts(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $taken = Job::factory()->create()->reference;

        $generator = new StaleReferenceGenerator($taken, staleCalls: PHP_INT_MAX);
        $this->app->instance(JobReferenceGenerator::class, $generator);

        try {
            $this->create();
            $this->fail('Expected UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(JobService::REFERENCE_ATTEMPTS, $generator->calls);
        }

        $this->assertSame(1, Job::query()->count());
    }
}

class StaleReferenceGenerator extends JobReferenceGenerator
{
    public int $calls = 0;

    public function __construct(
        private readonly string $stale,
        private readonly int $staleCalls,
    ) {}

    public function generate(CarbonInterface $on): string
    {
        $this->calls++;

        return $this->calls <= $this->staleCalls ? $this->stale : parent::generate($on);
    }
}
