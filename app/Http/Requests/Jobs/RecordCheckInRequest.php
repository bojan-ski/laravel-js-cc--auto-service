<?php

namespace App\Http\Requests\Jobs;

use App\Enums\FuelLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCheckInRequest extends FormRequest
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
            'fuel_level' => ['required', Rule::enum(FuelLevel::class)],
            'damage_notes' => ['nullable', 'string', 'max:2000'],
            'valuables' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fuel_level' => __('check_ins.fields.fuel_level'),
            'damage_notes' => __('check_ins.fields.damage_notes'),
            'valuables' => __('check_ins.fields.valuables'),
        ];
    }
}
