<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentValidationTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    /**
     * The security property this feature rests on, and the ONLY way to test it.
     * UploadedFile::fake() reports a MIME type derived from the filename, so a
     * fake called "evil.jpg" is seen as image/jpeg by both the `mimetypes` rule
     * and the service no matter what bytes it holds. These uploads are real
     * files whose contents contradict their names.
     *
     * @return array<string, array{string, string}>
     */
    public static function disguisedFiles(): array
    {
        return [
            'plain text named .jpg' => ['photo.jpg', 'just some text, definitely not an image'],
            'shell script named .png' => ['photo.png', "#!/bin/sh\nrm -rf /\n"],
            'windows executable named .jpg' => ['photo.jpg', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff"],
            'html named .webp' => ['photo.webp', '<html><script>alert(1)</script></html>'],
        ];
    }

    #[DataProvider('disguisedFiles')]
    public function test_a_disguised_file_is_rejected_on_its_detected_mime_type(string $filename, string $contents): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload($filename, $contents, 'image/jpeg'),
            ])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_an_image_over_ten_megabytes_is_rejected(): void
    {
        $this->assertUploadRejected('big.jpg', $this->paddedImage(11 * 1024 * 1024));
    }

    public function test_an_image_under_ten_megabytes_is_accepted(): void
    {
        $this->assertUploadAccepted('ok.jpg', $this->paddedImage(9 * 1024 * 1024));
    }

    public function test_a_pdf_of_fifteen_megabytes_is_accepted_because_documents_have_their_own_ceiling(): void
    {
        $this->assertUploadAccepted('big.pdf', $this->paddedPdf(15 * 1024 * 1024));
    }

    public function test_a_pdf_over_twenty_megabytes_is_rejected(): void
    {
        $this->assertUploadRejected('huge.pdf', $this->paddedPdf(21 * 1024 * 1024));
    }

    public function test_a_missing_file_is_rejected(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, JobAttachment::count());
    }

    public function test_the_validation_error_is_rendered_on_the_job_page(): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload('notes.txt', 'plain text'),
            ]);

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('id="attachment-file-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    private function assertUploadAccepted(string $filename, string $contents): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload($filename, $contents),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, JobAttachment::count());
    }

    private function assertUploadRejected(string $filename, string $contents): void
    {
        Storage::fake('local');

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.show', $job))
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realUpload($filename, $contents),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    private function paddedImage(int $bytes): string
    {
        $image = $this->imageBytes(200, 200);

        return $image.str_repeat("\x00", max(0, $bytes - strlen($image)));
    }

    private function paddedPdf(int $bytes): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\ntrailer<</Root 1 0 R>>\n";

        return $pdf.str_repeat("\x00", max(0, $bytes - strlen($pdf)));
    }
}
