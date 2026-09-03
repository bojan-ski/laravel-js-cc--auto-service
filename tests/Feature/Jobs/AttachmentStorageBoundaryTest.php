<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentStorageBoundaryTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    public function test_the_attachment_disk_is_private_and_outside_the_public_path(): void
    {
        $disk = config('attachments.disk');

        $this->assertSame('local', $disk);
        $this->assertSame(storage_path('app/private'), config("filesystems.disks.{$disk}.root"));
        $this->assertStringNotContainsString(public_path(), config("filesystems.disks.{$disk}.root"));
        $this->assertNotSame('public', config("filesystems.disks.{$disk}.visibility"));
    }

    public function test_the_local_disk_does_not_register_framework_routes(): void
    {
        $this->assertFalse(
            config('filesystems.disks.local.serve'),
            'A signed /storage URL is unguessable but not authorized — it checks no session, role or policy.',
        );
    }

    /**
     * Both doors, not just the read one: `serve => true` also registers
     * PUT /storage/{path} (storage.local.upload), a signed write endpoint
     * carrying no middleware at all.
     *
     * @return array<string, array{string}>
     */
    public static function storageRouteNames(): array
    {
        return [
            'read' => ['storage.local'],
            'upload' => ['storage.local.upload'],
        ];
    }

    #[DataProvider('storageRouteNames')]
    public function test_the_framework_storage_route_is_not_registered(string $name): void
    {
        $this->assertNull(
            Route::getRoutes()->getByName($name),
            "Route {$name} still exists, so there is a second door to staff-only files.",
        );
    }

    public function test_no_stored_file_is_reachable_under_slash_storage(): void
    {
        Storage::fake('local');

        $attachment = app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $this->realImageUpload('photo.jpg'),
            User::factory()->mechanic()->create(),
        );

        $user = User::factory()->admin()->create();

        foreach ($attachment->storedPaths() as $path) {
            $this->actingAs($user)->get('/storage/'.$path)->assertNotFound();
            $this->actingAs($user)->put('/storage/'.$path)->assertNotFound();
        }
    }

    public function test_stored_paths_live_under_the_configured_directory(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $attachment = app(AttachmentService::class)->upload(
            $job,
            $this->realImageUpload('photo.jpg'),
            User::factory()->mechanic()->create(),
        );

        foreach ($attachment->storedPaths() as $path) {
            $this->assertStringStartsWith(config('attachments.directory').'/'.$job->getKey().'/', $path);
        }
    }
}
