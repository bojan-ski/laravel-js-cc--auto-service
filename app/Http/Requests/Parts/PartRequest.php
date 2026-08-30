<?php

namespace App\Http\Requests\Parts;

use App\Models\Part;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartRequest extends FormRequest
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
        $sku = Rule::unique('parts', 'sku')->withoutTrashed();

        if ($this->part() !== null) {
            $sku->ignore($this->part());
        }

        return [
            'sku' => ['required', 'string', 'max:40', $sku],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => [
                Rule::prohibitedIf(fn (): bool => Gate::denies('viewCostPrice', Part::class)),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectDeletedConflict($validator),
        ];
    }

    private function part(): ?Part
    {
        $part = $this->route('part');

        return $part instanceof Part ? $part : null;
    }

    private function rejectDeletedConflict(Validator $validator): void
    {
        if ($validator->errors()->has('sku') || blank($this->input('sku'))) {
            return;
        }

        $conflict = Part::onlyTrashed()->where('sku', $this->input('sku'))->first();

        if ($conflict === null) {
            return;
        }

        $validator->errors()->add('sku', __('parts.validation.sku_deleted', [
            'name' => $conflict->name,
        ]));
    }
}
