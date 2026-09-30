<?php

namespace App\Http\Requests\Jobs;

use App\Enums\SignatureMethod;
use App\Services\CheckInService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature_method' => ['required', Rule::enum(SignatureMethod::class)],
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signature' => [
                'nullable',
                'required_if:signature_method,'.SignatureMethod::Drawn->value,
                'prohibited_if:signature_method,'.SignatureMethod::Paper->value,
                'string',
                'starts_with:"'.CheckInService::DATA_URL_PREFIX.'"',
                'max:700000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'signature_method' => __('check_ins.fields.signature_method'),
            'signed_by_name' => __('check_ins.fields.signed_by_name'),
            'signature' => __('check_ins.fields.signature'),
        ];
    }

    public function signatureMethod(): SignatureMethod
    {
        return SignatureMethod::from($this->validated('signature_method'));
    }
}
