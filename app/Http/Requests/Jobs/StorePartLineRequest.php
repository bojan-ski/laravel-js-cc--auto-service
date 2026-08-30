<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StorePartLineRequest extends JobItemRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'part_id' => ['required', 'integer', Rule::exists('parts', 'id')->whereNull('deleted_at')],
            ...$this->partLineRules(),
        ];
    }
}
