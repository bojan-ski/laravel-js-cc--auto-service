<?php

namespace Tests\Support;

use App\Services\CheckInService;

/**
 * Drawn signatures as the pad sends them: a PNG data URL. transparent() builds
 * one whose background has no alpha fill, so a test can prove the server
 * flattens it onto white rather than trusting the client to have done so.
 */
trait CreatesSignatures
{
    protected function signatureDataUrl(int $width = 600, int $height = 200, bool $transparent = false): string
    {
        return CheckInService::DATA_URL_PREFIX.base64_encode($this->signaturePng($width, $height, $transparent));
    }

    protected function signaturePng(int $width = 600, int $height = 200, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        }

        imagesetthickness($image, 3);
        imageline($image, 10, $height - 10, $width - 10, 10, (int) imagecolorallocate($image, 0, 0, 0));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
