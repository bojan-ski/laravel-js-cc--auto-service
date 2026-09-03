<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentVariantTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    public function test_the_thumbnail_is_exactly_400_by_300_webp(): void
    {
        $attachment = $this->uploadImage(2400, 900);

        [$width, $height, $mime] = $this->measure($attachment->variant_thumb_path);

        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
        $this->assertSame('image/webp', $mime);
    }

    /**
     * @return array<string, array{int, int, int, int}>
     */
    public static function fullVariantSizes(): array
    {
        return [
            'wide beyond the ceiling' => [2400, 900, 1600, 600],
            'tall beyond the ceiling' => [900, 2400, 600, 1600],
            'both edges beyond' => [3200, 3200, 1600, 1600],
        ];
    }

    #[DataProvider('fullVariantSizes')]
    public function test_the_full_variant_fits_the_ceiling_and_keeps_its_aspect_ratio(
        int $sourceWidth,
        int $sourceHeight,
        int $expectedWidth,
        int $expectedHeight,
    ): void {
        $attachment = $this->uploadImage($sourceWidth, $sourceHeight);

        [$width, $height, $mime] = $this->measure($attachment->variant_full_path);

        $this->assertSame($expectedWidth, $width);
        $this->assertSame($expectedHeight, $height);
        $this->assertSame('image/webp', $mime);
        $this->assertSame(
            round($sourceWidth / $sourceHeight, 2),
            round($width / $height, 2),
            'The full variant must preserve the source aspect ratio.',
        );
    }

    public function test_an_image_smaller_than_the_ceiling_is_not_upscaled(): void
    {
        $attachment = $this->uploadImage(320, 240);

        [$width, $height] = $this->measure($attachment->variant_full_path);

        $this->assertSame(320, $width);
        $this->assertSame(240, $height);
    }

    public function test_the_thumbnail_crops_rather_than_squashing_a_source_of_another_shape(): void
    {
        $attachment = $this->uploadImage(1000, 1000);

        [$width, $height] = $this->measure($attachment->variant_thumb_path);

        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
    }

    public function test_the_stored_original_keeps_its_own_format_and_dimensions(): void
    {
        $attachment = $this->uploadImage(1200, 800);

        [$width, $height, $mime] = $this->measure($attachment->path);

        $this->assertSame(1200, $width);
        $this->assertSame(800, $height);
        $this->assertSame('image/jpeg', $mime);
    }

    private function uploadImage(int $width, int $height): JobAttachment
    {
        Storage::fake('local');

        return app(AttachmentService::class)->upload(
            Job::factory()->inProgress()->create(),
            $this->realImageUpload('source.jpg', $width, $height),
            User::factory()->mechanic()->create(),
        );
    }

    /**
     * @return array{int, int, string}
     */
    private function measure(string $path): array
    {
        $image = (new ImageManager(Driver::class))
            ->decodeBinary(Storage::disk('local')->get($path));

        return [$image->width(), $image->height(), $image->origin()->mediaType()];
    }
}
