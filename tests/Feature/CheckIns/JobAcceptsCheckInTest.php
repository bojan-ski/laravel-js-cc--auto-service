<?php

namespace Tests\Feature\CheckIns;

use App\Models\Job;
use App\Models\JobCheckIn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The state table from the spec, with C2's late signing: save and photos need
 * a working, unlocked job with no signature; signing needs an existing
 * unsigned sheet on an unlocked job that is working or completed.
 */
class JobAcceptsCheckInTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: bool, 2: string, 3: bool, 4: bool}>
     */
    public static function states(): array
    {
        $rows = [];

        foreach (['received', 'inProgress', 'awaitingParts'] as $status) {
            $rows["{$status}, no sheet"] = [$status, false, 'none', true, false];
            $rows["{$status}, unsigned"] = [$status, false, 'unsigned', true, true];
            $rows["{$status}, signed"] = [$status, false, 'signed', false, false];
        }

        $rows['completed, no sheet'] = ['completed', false, 'none', false, false];
        $rows['completed, unsigned'] = ['completed', false, 'unsigned', false, true];
        $rows['completed, signed'] = ['completed', false, 'signed', false, false];
        $rows['locked, no sheet'] = ['completed', true, 'none', false, false];
        $rows['locked, unsigned'] = ['completed', true, 'unsigned', false, false];
        $rows['locked, signed'] = ['completed', true, 'signed', false, false];
        $rows['cancelled, no sheet'] = ['cancelled', false, 'none', false, false];
        $rows['cancelled, unsigned'] = ['cancelled', false, 'unsigned', false, false];
        $rows['cancelled, signed'] = ['cancelled', false, 'signed', false, false];

        return $rows;
    }

    #[DataProvider('states')]
    public function test_each_state_accepts_exactly_what_the_table_says(string $status, bool $locked, string $sheet, bool $record, bool $sign): void
    {
        $job = $this->job($status, $locked, $sheet);

        $this->assertSame($record, $job->acceptsCheckIn(), 'acceptsCheckIn, relation unloaded');
        $this->assertSame($sign, $job->acceptsCheckInSignature(), 'acceptsCheckInSignature, relation unloaded');

        $loaded = Job::query()->with('checkIn')->findOrFail($job->getKey());

        $this->assertSame($record, $loaded->acceptsCheckIn(), 'acceptsCheckIn, relation loaded');
        $this->assertSame($sign, $loaded->acceptsCheckInSignature(), 'acceptsCheckInSignature, relation loaded');
    }

    public function test_the_loaded_relation_is_read_instead_of_querying_again(): void
    {
        $job = $this->job('inProgress', false, 'none');
        $job->load('checkIn');

        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->assertTrue($job->acceptsCheckIn(), 'The loaded (empty) relation was not the one read.');
        $this->assertFalse($job->fresh()->acceptsCheckIn());
    }

    private function job(string $status, bool $locked, string $sheet): Job
    {
        $factory = Job::factory()->{$status}();
        $job = ($locked ? $factory->locked() : $factory)->create();

        match ($sheet) {
            'unsigned' => JobCheckIn::factory()->forJob($job)->create(),
            'signed' => JobCheckIn::factory()->forJob($job)->signedOnPaper()->create(),
            default => null,
        };

        return $job->fresh();
    }
}
