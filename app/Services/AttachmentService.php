<?php

namespace App\Services;

use App\Enums\AttachmentCategory;
use App\Enums\AttachmentType;
use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableAttachment;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class AttachmentService
{
    private const int THUMB_WIDTH = 400;

    private const int THUMB_HEIGHT = 300;

    private const int FULL_MAX_EDGE = 1600;

    private const int WEBP_QUALITY = 80;

    /**
     * @param  (Closure(): void)|null  $inside
     */
    public function upload(
        Job $job,
        UploadedFile $file,
        User $uploadedBy,
        AttachmentCategory $category = AttachmentCategory::General,
        ?Closure $inside = null,
    ): JobAttachment {
        $mimeType = (string) $file->getMimeType();
        $type = AttachmentType::forMimeType($mimeType);

        if ($type === null) {
            throw new UnreadableAttachment($file->getClientOriginalName(), $mimeType);
        }

        $variants = $type->hasVariants() ? $this->encodeVariants($file, $mimeType) : [];

        $attachment = new JobAttachment;
        $attachment->job_id = $job->getKey();
        $attachment->type = $type;
        $attachment->category = $category;
        $attachment->original_filename = $file->getClientOriginalName();
        $attachment->mime_type = $mimeType;
        $attachment->size_bytes = (int) $file->getSize();
        $attachment->uploaded_by = $uploadedBy->getKey();

        $written = [];

        try {
            DB::transaction(function () use ($attachment, $file, $job, $variants, $inside, &$written): void {
                if ($inside !== null) {
                    $inside();
                }

                $directory = config('attachments.directory').'/'.$job->getKey();
                $key = (string) Str::ulid();

                $path = $this->disk()->putFileAs($directory, $file, $key.'.'.$file->extension());

                if ($path === false) {
                    throw new RuntimeException("Failed to store attachment for job {$job->getKey()}.");
                }

                $attachment->path = $path;
                $written[] = $path;

                foreach ($variants as $variant => $bytes) {
                    $variantPath = "{$directory}/{$key}_{$variant}.webp";

                    if ($this->disk()->put($variantPath, $bytes) === false) {
                        throw new RuntimeException("Failed to store the {$variant} variant for job {$job->getKey()}.");
                    }

                    $written[] = $variantPath;

                    $attachment->setAttribute("variant_{$variant}_path", $variantPath);
                }

                $attachment->save();
            });
        } catch (Throwable $e) {
            $this->disk()->delete($written);

            throw $e;
        }

        return $attachment;
    }

    public function delete(JobAttachment $attachment): void
    {
        $paths = $attachment->storedPaths();

        DB::transaction(function () use ($attachment): void {
            if ($attachment->isCheckInPhoto()) {
                $job = Job::query()->whereKey($attachment->job_id)->lockForUpdate()->firstOrFail();

                if (! $job->acceptsCheckIn()) {
                    throw new CheckInNotOpen($job);
                }
            }

            $attachment->delete();
        });

        $this->disk()->delete($paths);
    }

    /**
     * @return array<string, string>
     */
    private function encodeVariants(UploadedFile $file, string $mimeType): array
    {
        $source = (string) $file->getRealPath();
        $manager = new ImageManager(Driver::class);

        try {
            return [
                'thumb' => (string) $manager->decodePath($source)
                    ->cover(self::THUMB_WIDTH, self::THUMB_HEIGHT)
                    ->encode(new WebpEncoder(quality: self::WEBP_QUALITY)),
                'full' => (string) $manager->decodePath($source)
                    ->scaleDown(self::FULL_MAX_EDGE, self::FULL_MAX_EDGE)
                    ->encode(new WebpEncoder(quality: self::WEBP_QUALITY)),
            ];
        } catch (DriverException $e) {
            throw new UnreadableAttachment($file->getClientOriginalName(), $mimeType, $e);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('attachments.disk'));
    }
}
