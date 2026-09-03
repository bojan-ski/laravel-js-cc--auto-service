<?php

namespace Tests\Feature\Jobs;

use App\Enums\AttachmentType;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function photoFormats(): array
    {
        return [
            'jpeg' => ['jpeg', 'bumper.jpg', 'image/jpeg'],
            'png' => ['png', 'bumper.png', 'image/png'],
            'webp' => ['webp', 'bumper.webp', 'image/webp'],
        ];
    }

    #[DataProvider('photoFormats')]
    public function test_each_accepted_image_format_stores_three_files_and_both_variant_columns(
        string $format,
        string $filename,
        string $expectedMime,
    ): void {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realImageUpload($filename, 800, 600, $format),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $attachment = JobAttachment::sole();

        $this->assertSame(AttachmentType::Photo, $attachment->type);
        $this->assertSame($expectedMime, $attachment->mime_type);
        $this->assertNotNull($attachment->variant_thumb_path);
        $this->assertNotNull($attachment->variant_full_path);
        $this->assertCount(3, $attachment->storedPaths());

        foreach ($attachment->storedPaths() as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_a_pdf_stores_one_file_and_leaves_both_variant_columns_null(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload('inspection.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\ntrailer<</Root 1 0 R>>"),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $attachment = JobAttachment::sole();

        $this->assertSame(AttachmentType::Document, $attachment->type);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertNull($attachment->variant_thumb_path);
        $this->assertNull($attachment->variant_full_path);
        $this->assertCount(1, $attachment->storedPaths());

        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_the_upload_records_the_original_filename_size_and_acting_user(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $bytes = $this->imageBytes(800, 600);

        $this->actingAs($mechanic)
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload('front-left-wing.jpg', $bytes),
            ]);

        $attachment = JobAttachment::sole();

        $this->assertSame('front-left-wing.jpg', $attachment->original_filename);
        $this->assertSame(strlen($bytes), $attachment->size_bytes);
        $this->assertSame($mechanic->getKey(), $attachment->uploaded_by);
        $this->assertSame($job->getKey(), $attachment->job_id);
    }

    public function test_the_type_comes_from_the_file_and_a_contradicting_client_type_is_ignored(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload(
                    'report.pdf',
                    "%PDF-1.4\n%\xE2\xE3\xCF\xD3\ntrailer<</Root 1 0 R>>",
                    'image/jpeg',
                ),
                'type' => 'photo',
            ]);

        $attachment = JobAttachment::sole();

        $this->assertSame(AttachmentType::Document, $attachment->type);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertNull($attachment->variant_thumb_path);
    }

    public function test_stored_paths_are_generated_and_never_taken_from_the_uploaded_filename(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realImageUpload('../../etc/passwd photo.jpg'),
            ]);

        $attachment = JobAttachment::sole();

        $this->assertStringStartsWith("job-attachments/{$job->getKey()}/", $attachment->path);
        $this->assertStringNotContainsString('passwd', $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
    }

    /**
     * What this proves: every server-controlled column stays unfillable, so a
     * future `#[Fillable(['path'])]` fails here rather than in production.
     *
     * What it does NOT prove — the trap spec 15 recorded: deleting
     * `#[Fillable([])]` outright leaves this green, because Eloquent already
     * defaults to `$fillable = []` with `$guarded = ['*']`.
     */
    #[DataProvider('serverControlledColumns')]
    public function test_no_column_is_mass_assignable(string $column): void
    {
        $this->expectException(MassAssignmentException::class);

        (new JobAttachment)->fill([$column => 'hijacked']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function serverControlledColumns(): array
    {
        return [
            'job_id' => ['job_id'],
            'type' => ['type'],
            'path' => ['path'],
            'variant_full_path' => ['variant_full_path'],
            'variant_thumb_path' => ['variant_thumb_path'],
            'original_filename' => ['original_filename'],
            'mime_type' => ['mime_type'],
            'size_bytes' => ['size_bytes'],
            'uploaded_by' => ['uploaded_by'],
        ];
    }
}
