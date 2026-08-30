<?php

namespace App\Http\Requests\Parts;

use App\Enums\StockMovementReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantity = $this->input('quantity_change');

        if (is_string($quantity) && filter_var($quantity, FILTER_VALIDATE_INT) !== false) {
            $this->merge(['quantity_change' => (int) $quantity]);
        }

        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isRestock = $this->input('reason') === StockMovementReason::Restock->value;

        return [
            'reason' => ['required', Rule::in(StockMovementReason::recordableByHandValues())],
            'quantity_change' => [
                'required',
                'integer',
                'between:-999999,999999',
                $isRestock ? 'min:1' : Rule::notIn([0]),
            ],
            'note' => [
                'required_if:reason,'.StockMovementReason::ManualAdjustment->value,
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function reason(): StockMovementReason
    {
        return StockMovementReason::from($this->validated('reason'));
    }
}
