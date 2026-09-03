<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentDeletionTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    public function test_deleting_a_photo_removes_the_row_and_all_three_files(): void
    {
        Storage::fake('local');

        $attachment = $this->upload($this->realImageUpload('photo.jpg'));
        $paths = $attachment->storedPaths();

        $this->assertCount(3, $paths);

        app(AttachmentService::class)->delete($attachment);

        $this->assertSame(0, JobAttachment::count());

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_deleting_a_document_removes_its_single_file(): void
    {
        Storage::fake('local');

        $attachment = $this->upload($this->realUpload('report.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\ntrailer<</Root 1 0 R>>"));
        $path = $attachment->path;

        app(AttachmentService::class)->delete($attachment);

        $this->assertSame(0, JobAttachment::count());
        Storage::disk('local')->assertMissing($path);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_deleting_one_attachment_leaves_its_siblings_files_untouched(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();
        $user = User::factory()->mechanic()->create();
        $service = app(AttachmentService::class);

        $doomed = $service->upload($job, $this->realImageUpload('one.jpg'), $user);
        $keeper = $service->upload($job, $this->realImageUpload('two.jpg'), $user);

        $service->delete($doomed);

        foreach ($keeper->storedPaths() as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $this->assertSame([$keeper->getKey()], JobAttachment::pluck('id')->all());
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_deletion_through_the_route_removes_the_files_too(): void
    {
        Storage::fake('local');

        $mechanic = User::factory()->mechanic()->create();
        $attachment = $this->upload($this->realImageUpload('photo.jpg'), $mechanic);
        $paths = $attachment->storedPaths();

        $this->actingAs($mechanic)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect(route('jobs.show', $attachment->job_id));

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    /**
     * The row is deleted first, and the files only after that transaction
     * commits. The reverse order loses the bytes and then fails to remove the
     * record, leaving a row pointing at nothing; this order can at worst orphan
     * a file, which the spec calls "just waste".
     *
     * Nothing in the schema can make the row delete fail on its own — no
     * foreign key references job_attachments — so the failure is injected.
     */
    public function test_a_failed_row_delete_leaves_every_file_in_place(): void
    {
        Storage::fake('local');

        $attachment = $this->upload($this->realImageUpload('photo.jpg'));
        $paths = $attachment->storedPaths();

        JobAttachment::deleting(function (): never {
            throw new RuntimeException('row delete failed');
        });

        try {
            app(AttachmentService::class)->delete($attachment);
            $this->fail('The injected delete failure did not surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('row delete failed', $e->getMessage());
        }

        $this->assertSame(1, JobAttachment::count(), 'The row must survive its own failed delete.');

        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    protected function tearDown(): void
    {
        JobAttachment::flushEventListeners();

        parent::tearDown();
    }

    private function upload(UploadedFile $file, ?User $uploader = null): JobAttachment
    {
        return app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $file,
            $uploader ?? User::factory()->mechanic()->create(),
        );
    }
}
