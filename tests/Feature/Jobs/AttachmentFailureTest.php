<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\UnreadableAttachment;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentFailureTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    public function test_a_truncated_image_leaves_no_row_and_no_file(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        try {
            app(AttachmentService::class)->upload(
                $job,
                $this->realUpload('broken.jpg', substr($this->imageBytes(600, 400), 0, 40)),
                User::factory()->mechanic()->create(),
            );

            $this->fail('A truncated image was accepted.');
        } catch (UnreadableAttachment) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_the_controller_flashes_an_error_rather_than_500ing(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        $truncated = substr($this->imageBytes(600, 400), 0, 40);

        $this->actingAs($mechanic)
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload('broken.jpg', $truncated, 'image/jpeg'),
            ])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('error', __('jobs.attachments.flash.unreadable'));

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    /**
     * The local disk is configured with 'throw' => false, so a failed write
     * returns false rather than raising. Without an explicit check that lands
     * in the non-nullable `path` column as an empty string — a row pointing at
     * nothing, which is exactly what the deletion ordering exists to prevent.
     */
    public function test_a_failed_disk_write_aborts_the_upload_rather_than_storing_an_empty_path(): void
    {
        Storage::fake('local');

        Storage::shouldReceive('disk')->andReturn($disk = \Mockery::mock(
            Filesystem::class
        ));
        $disk->shouldReceive('putFileAs')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(true);
        $disk->shouldReceive('delete')->andReturn(true);

        $job = Job::factory()->inProgress()->create();

        $this->expectException(\RuntimeException::class);

        try {
            app(AttachmentService::class)->upload(
                $job,
                $this->realImageUpload('photo.jpg'),
                User::factory()->mechanic()->create(),
            );
        } finally {
            $this->assertSame(0, JobAttachment::count());
        }
    }

    public function test_a_failed_variant_write_aborts_the_upload(): void
    {
        Storage::fake('local');

        Storage::shouldReceive('disk')->andReturn($disk = \Mockery::mock(
            Filesystem::class
        ));
        $disk->shouldReceive('putFileAs')->andReturn('job-attachments/1/x.jpg');
        $disk->shouldReceive('put')->andReturn(false);
        $disk->shouldReceive('delete')->andReturn(true);

        $job = Job::factory()->inProgress()->create();

        $this->expectException(\RuntimeException::class);

        try {
            app(AttachmentService::class)->upload(
                $job,
                $this->realImageUpload('photo.jpg'),
                User::factory()->mechanic()->create(),
            );
        } finally {
            $this->assertSame(
                0,
                JobAttachment::count(),
                'A row must never record a variant path for a file that was not written.',
            );
        }
    }

    /**
     * Unreachable through HTTP — StoreAttachmentRequest's `mimetypes` rule is
     * built from the same enum, so validation always rejects first. Tested at
     * the service boundary because deleting the guard turns a catchable
     * UnreadableAttachment into a fatal "hasVariants() on null".
     */
    public function test_the_service_itself_refuses_an_unrecognised_mime_type(): void
    {
        Storage::fake('local');

        $this->expectException(UnreadableAttachment::class);

        app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $this->realUpload('notes.txt', 'plain text, not an accepted type'),
            User::factory()->mechanic()->create(),
        );
    }

    public function test_a_row_insert_failure_removes_every_file_already_written(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();
        $user = User::factory()->mechanic()->create();

        $job->forceFill(['id' => 987654321]);

        try {
            app(AttachmentService::class)->upload($job, $this->realImageUpload('photo.jpg'), $user);

            $this->fail('An attachment was written against a job that does not exist.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(UnreadableAttachment::class, $e);
        }

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(
            Storage::disk('local')->allFiles(),
            'A failed insert must not leave the three written files behind.',
        );
    }
}
