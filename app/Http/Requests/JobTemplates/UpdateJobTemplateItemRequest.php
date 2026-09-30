<?php

namespace App\Http\Requests\JobTemplates;

use App\Enums\JobItemType;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateJobTemplateItemRequest extends JobTemplateItemRequest
{
    public function type(): JobItemType
    {
        return $this->route('item')->type;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->rulesFor($this->type());
    }
}
