<?php

namespace App\Http\Requests\JobTemplates;

use App\Enums\JobItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreJobTemplateItemRequest extends JobTemplateItemRequest
{
    public function type(): ?JobItemType
    {
        return is_string($this->input('type')) ? JobItemType::tryFrom($this->input('type')) : null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = ['type' => ['required', Rule::enum(JobItemType::class)]];

        return $this->type() === null ? $type : [...$type, ...$this->rulesFor($this->type())];
    }
}
