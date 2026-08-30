<?php

namespace App\Http\Requests\Jobs;

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
        $item = $this->route('jobItem');

        if (! $item instanceof JobItem || ! $item->isPart()) {
            return parent::rules();
        }

        return [
            'part_id' => ['prohibited'],
            ...$this->partLineRules(),
        ];
    }
}
