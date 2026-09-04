<?php

namespace App\Http\Requests\Parts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PartImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A semicolon-delimited CSV — what Excel writes on a European locale —
     * reports `text/plain`, not `text/csv`, so `text/plain` must be accepted
     * and the MIME rule cannot be the real gate. The header row is: an
     * arbitrary text file passes here and is then rejected by name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'extensions:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
                'max:'.(int) config('imports.max_kilobytes'),
            ],
        ];
    }
}
