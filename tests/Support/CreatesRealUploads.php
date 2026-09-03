<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * UploadedFile::fake() cannot exercise MIME detection at all.
 * Illuminate\Http\Testing\File::getMimeType() returns
 * `$this->mimeTypeToReport ?: MimeType::from($this->name)` — the FILENAME —
 * so a fake named "evil.jpg" reports image/jpeg whatever bytes it holds, and
 * both the `mimetypes` rule and AttachmentService read that same lie.
 *
 * These helpers build a real Illuminate\Http\UploadedFile over a real temp
 * file. That class does not override getMimeType(), so Symfony guesses from
 * CONTENT — which is the property this feature actually depends on.
 */
trait CreatesRealUploads
{
    /** @var list<string> */
    private array $temporaryUploadPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploadPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryUploadPaths = [];

        parent::tearDown();
    }

    protected function realUpload(string $filename, string $contents, ?string $clientMimeType = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        $this->temporaryUploadPaths[] = $path;

        return new UploadedFile($path, $filename, $clientMimeType, null, true);
    }

    protected function realImageUpload(
        string $filename,
        int $width = 800,
        int $height = 600,
        string $format = 'jpeg',
        ?string $clientMimeType = null,
    ): UploadedFile {
        return $this->realUpload($filename, $this->imageBytes($width, $height, $format), $clientMimeType);
    }

    protected function imageBytes(int $width, int $height, string $format = 'jpeg'): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 180));

        ob_start();

        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image, null, 90),
        };

        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
