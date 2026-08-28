<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class JobItemRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'unit_price' => [
                Rule::prohibitedIf(fn (): bool => ! $this->user()->isFrontOffice()),
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:99999999.99',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isFrontOffice()) {
            $this->merge(['user_id' => $this->user()->getKey()]);
        }
    }
}
