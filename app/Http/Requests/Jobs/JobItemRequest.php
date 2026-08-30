<?php

namespace App\Http\Requests\Jobs;

use App\Enums\JobItemType;
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
            'quantity' => JobItemType::Labor->quantityRules(),
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'unit_price' => $this->unitPriceRules(),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function partLineRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => JobItemType::Part->quantityRules(),
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'unit_price' => $this->unitPriceRules(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function unitPriceRules(): array
    {
        return [
            Rule::prohibitedIf(fn (): bool => ! $this->user()->isFrontOffice()),
            'nullable',
            'numeric',
            'decimal:0,2',
            'min:0',
            'max:99999999.99',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isFrontOffice()) {
            $this->merge(['user_id' => $this->user()->getKey()]);
        }
    }
}
