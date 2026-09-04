<?php

namespace App\Http\Requests\Admin;

use App\Enums\QualityTier;
use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BrandRequest extends FormRequest
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
        $name = Rule::unique('brands', 'name')->withoutTrashed();

        if ($this->route('brand') !== null) {
            $name->ignore($this->route('brand'));
        }

        return [
            'name' => ['required', 'string', 'max:80', $name],
            'quality_tier' => ['required', Rule::enum(QualityTier::class)],
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

    private function rejectDeletedConflict(Validator $validator): void
    {
        if ($validator->errors()->has('name') || blank($this->input('name'))) {
            return;
        }

        $conflict = Brand::onlyTrashed()->where('name', $this->input('name'))->first();

        if ($conflict === null) {
            return;
        }

        $validator->errors()->add('name', __('brands.validation.name_deleted'));
    }
}
