<?php

namespace Tests\Feature\CheckIns;

use App\Models\Job;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInSignatureRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_every_role_reads_a_drawn_signature_as_a_png(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->signedByDrawing()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.check-in.signature', $job))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sheetsWithoutADrawing(): array
    {
        return ['no sheet' => ['none'], 'unsigned' => ['unsigned'], 'signed on paper' => ['paper']];
    }

    #[DataProvider('sheetsWithoutADrawing')]
    public function test_there_is_no_image_without_a_drawn_signature(string $sheet): void
    {
        $job = Job::factory()->inProgress()->create();

        match ($sheet) {
            'unsigned' => JobCheckIn::factory()->forJob($job)->create(),
            'paper' => JobCheckIn::factory()->forJob($job)->signedOnPaper()->create(),
            default => null,
        };

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.check-in.signature', $job))
            ->assertNotFound();
    }
}
