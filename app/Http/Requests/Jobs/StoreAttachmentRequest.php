<?php

namespace App\Http\Requests\Jobs;

use App\Enums\AttachmentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', AttachmentType::allowedMimeTypes()),
                'max:'.$this->maxKilobytes(),
            ],
        ];
    }

    private function maxKilobytes(): int
    {
        $file = $this->file('file');

        $type = $file instanceof UploadedFile
            ? AttachmentType::forMimeType($file->getMimeType())
            : null;

        return $type?->maxKilobytes() ?? max(array_map(
            fn (AttachmentType $case): int => $case->maxKilobytes(),
            AttachmentType::cases(),
        ));
    }
}
