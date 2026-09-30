<?php

namespace Tests\Feature\Jobs;

use App\Enums\AttachmentCategory;
use App\Enums\AttachmentType;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobAttachmentModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_path_for_returns_the_matching_column_on_a_photo(): void
    {
        $photo = JobAttachment::factory()->photo()->make();

        $this->assertSame($photo->path, $photo->pathFor('original'));
        $this->assertSame($photo->variant_full_path, $photo->pathFor('full'));
        $this->assertSame($photo->variant_thumb_path, $photo->pathFor('thumb'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function variantsADocumentLacks(): array
    {
        return [
            'full' => ['full'],
            'thumb' => ['thumb'],
        ];
    }

    #[DataProvider('variantsADocumentLacks')]
    public function test_path_for_returns_null_rather_than_falling_back_on_a_document(string $variant): void
    {
        $document = JobAttachment::factory()->document()->make();

        $this->assertNull(
            $document->pathFor($variant),
            'A silent fallback would serve a PDF where an image was expected.',
        );
        $this->assertNotNull($document->pathFor('original'));
    }

    public function test_path_for_returns_null_for_an_unknown_variant(): void
    {
        $photo = JobAttachment::factory()->photo()->make();

        $this->assertNull($photo->pathFor('nonsense'));
        $this->assertNull($photo->pathFor(''));
    }

    public function test_stored_paths_lists_three_files_for_a_photo_and_one_for_a_document(): void
    {
        $photo = JobAttachment::factory()->photo()->make();
        $document = JobAttachment::factory()->document()->make();

        $this->assertSame(
            [$photo->path, $photo->variant_full_path, $photo->variant_thumb_path],
            $photo->storedPaths(),
        );

        $this->assertSame([$document->path], $document->storedPaths());
    }

    public function test_the_type_predicates_agree_with_the_cast_enum(): void
    {
        $photo = JobAttachment::factory()->photo()->make();
        $document = JobAttachment::factory()->document()->make();

        $this->assertSame(AttachmentType::Photo, $photo->type);
        $this->assertTrue($photo->isPhoto());
        $this->assertFalse($photo->isDocument());

        $this->assertSame(AttachmentType::Document, $document->type);
        $this->assertTrue($document->isDocument());
        $this->assertFalse($document->isPhoto());
    }

    /**
     * attachments() is no longer loaded by the job page, which reads
     * generalAttachments() instead, so this is the only assertion on it.
     * Another job's attachment proves each relation is scoped to its job.
     */
    public function test_the_attachment_relations_split_by_category_in_id_order(): void
    {
        $job = Job::factory()->inProgress()->create();

        $document = JobAttachment::factory()->document()->forJob($job)->create();
        $checkInPhoto = JobAttachment::factory()->checkIn()->forJob($job)->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        JobAttachment::factory()->photo()->create();
        JobAttachment::factory()->checkIn()->create();

        $this->assertSame([$document->getKey(), $photo->getKey()], $job->generalAttachments()->pluck('id')->all());
        $this->assertSame([$checkInPhoto->getKey()], $job->checkInPhotos()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$document->getKey(), $checkInPhoto->getKey(), $photo->getKey()],
            $job->attachments()->pluck('id')->all(),
        );
    }

    public function test_a_new_attachment_is_general_and_the_category_predicate_agrees(): void
    {
        $general = JobAttachment::factory()->photo()->create()->fresh();
        $checkIn = JobAttachment::factory()->checkIn()->create()->fresh();

        $this->assertSame(AttachmentCategory::General, $general->category);
        $this->assertFalse($general->isCheckInPhoto());
        $this->assertSame(AttachmentCategory::CheckIn, $checkIn->category);
        $this->assertTrue($checkIn->isCheckInPhoto());
    }

    public function test_every_attachment_type_resolves_a_label(): void
    {
        foreach (AttachmentType::cases() as $type) {
            $this->assertNotSame(
                'jobs.attachments.types.'.$type->value,
                $type->label(),
                "AttachmentType::{$type->name} has no lang key.",
            );
        }
    }

    public function test_is_uploaded_by_matches_only_the_uploader(): void
    {
        $uploader = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();

        $attachment = JobAttachment::factory()->photo()->uploadedBy($uploader)->create();

        $this->assertTrue($attachment->isUploadedBy($uploader));
        $this->assertFalse($attachment->isUploadedBy($colleague));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function fileSizes(): array
    {
        return [
            'the visual spec\'s 1.2 MB' => [1258291, '1.2 MB'],
            'the visual spec\'s 840 KB' => [860160, '840 KB'],
            'a whole megabyte keeps no decimal' => [1048576, '1 MB'],
            'bytes' => [512, '512 B'],
        ];
    }

    #[DataProvider('fileSizes')]
    public function test_human_size_renders_the_documented_units(int $bytes, string $expected): void
    {
        $attachment = JobAttachment::factory()->document()->make(['size_bytes' => $bytes]);

        $this->assertSame($expected, $attachment->humanSize());
    }

    public function test_a_short_filename_is_left_alone(): void
    {
        $attachment = JobAttachment::factory()->document()->make([
            'original_filename' => 'inspection-report.pdf',
        ]);

        $this->assertSame('inspection-report.pdf', $attachment->displayFilename());
    }

    public function test_a_long_filename_is_truncated_in_the_middle_so_the_extension_survives(): void
    {
        $attachment = JobAttachment::factory()->document()->make([
            'original_filename' => 'front-left-wing-damage-before-repair-2026-09-02-final.pdf',
        ]);

        $display = $attachment->displayFilename();

        $this->assertLessThanOrEqual(JobAttachment::FILENAME_DISPLAY_LENGTH, mb_strlen($display));
        $this->assertStringEndsWith('.pdf', $display);
        $this->assertStringStartsWith('front-left', $display);
        $this->assertStringContainsString('…', $display);
    }
}
