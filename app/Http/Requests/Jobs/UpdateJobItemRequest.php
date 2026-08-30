<?php

namespace App\Http\Requests\Jobs;

use App\Enums\JobItemType;
use App\Models\JobItem;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateJobItemRequest extends JobItemRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->lineType() !== JobItemType::Part) {
            return parent::rules();
        }

        return [
            'part_id' => ['prohibited'],
            ...$this->partLineRules(),
        ];
    }

    /**
     * Read as a scalar rather than binding the model: a hydrated JobItem carries
     * unit_price, and this request is reached by mechanics.
     */
    private function lineType(): ?JobItemType
    {
        return JobItem::query()->whereKey($this->route('jobItem'))->value('type');
    }
}
