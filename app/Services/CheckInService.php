<?php

namespace App\Services;

use App\Enums\AttachmentCategory;
use App\Enums\SignatureMethod;
use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableSignature;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class CheckInService
{
    public const string DATA_URL_PREFIX = 'data:image/png;base64,';

    public const int MAX_SIGNATURE_BYTES = 512 * 1024;

    public const int MAX_SIGNATURE_WIDTH = 2000;

    public const int MAX_SIGNATURE_HEIGHT = 1000;

    public function __construct(
        private AttachmentService $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Job $job, User $actor, array $attributes): JobCheckIn
    {
        return DB::transaction(function () use ($job, $actor, $attributes): JobCheckIn {
            $locked = $this->lockJob($job);

            if (! $locked->acceptsCheckIn()) {
                throw new CheckInNotOpen($locked);
            }

            $checkIn = $locked->checkIn ?? new JobCheckIn;

            if (! $checkIn->exists) {
                $checkIn->job_id = $locked->getKey();
                $checkIn->recorded_by = $actor->getKey();
            }

            $checkIn->fill($attributes);
            $checkIn->save();

            return $checkIn;
        });
    }

    public function addPhoto(Job $job, UploadedFile $photo, User $actor): JobAttachment
    {
        return $this->attachments->upload($job, $photo, $actor, AttachmentCategory::CheckIn, function () use ($job): void {
            $locked = $this->lockJob($job);

            if ($locked->checkIn === null || ! $locked->acceptsCheckIn()) {
                throw new CheckInNotOpen($locked);
            }
        });
    }

    public function sign(Job $job, User $actor, SignatureMethod $method, string $signedByName, ?string $signatureDataUrl): JobCheckIn
    {
        $png = $method === SignatureMethod::Drawn
            ? $this->normalizeSignature((string) $signatureDataUrl)
            : null;

        $written = null;

        try {
            return DB::transaction(function () use ($job, $method, $signedByName, $png, &$written): JobCheckIn {
                $locked = $this->lockJob($job);

                if (! $locked->acceptsCheckInSignature()) {
                    throw new CheckInNotOpen($locked);
                }

                $checkIn = $locked->checkIn;

                if ($png !== null) {
                    $written = config('attachments.directory')."/{$locked->getKey()}/signature_".Str::ulid().'.png';

                    if ($this->disk()->put($written, $png) === false) {
                        throw new RuntimeException("Failed to store the signature for job {$locked->getKey()}.");
                    }
                }

                $checkIn->signed_by_name = $signedByName;
                $checkIn->signature_method = $method;
                $checkIn->signature_path = $written;
                $checkIn->signed_at = now();
                $checkIn->save();

                return $checkIn;
            });
        } catch (Throwable $e) {
            if ($written !== null) {
                $this->disk()->delete($written);
            }

            throw $e;
        }
    }

    private function normalizeSignature(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, self::DATA_URL_PREFIX)) {
            throw new UnreadableSignature('not a PNG data URL');
        }

        $bytes = base64_decode(substr($dataUrl, strlen(self::DATA_URL_PREFIX)), true);

        if ($bytes === false || $bytes === '') {
            throw new UnreadableSignature('not valid base64');
        }

        if (strlen($bytes) > self::MAX_SIGNATURE_BYTES) {
            throw new UnreadableSignature('over the size limit');
        }

        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[2] !== IMAGETYPE_PNG) {
            throw new UnreadableSignature('not a PNG image');
        }

        if ($size[0] > self::MAX_SIGNATURE_WIDTH || $size[1] > self::MAX_SIGNATURE_HEIGHT) {
            throw new UnreadableSignature('over the dimension limit');
        }

        try {
            return (string) (new ImageManager(Driver::class))
                ->decodeBinary($bytes)
                ->fillTransparentAreas('ffffff')
                ->encode(new PngEncoder);
        } catch (ImageException $e) {
            throw new UnreadableSignature('the image could not be decoded', $e);
        }
    }

    private function lockJob(Job $job): Job
    {
        return Job::query()
            ->with('checkIn')
            ->whereKey($job->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('attachments.disk'));
    }
}
