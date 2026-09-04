<?php

namespace App\Http\Requests\Parts;

use App\Enums\PartNumberType;
use App\Models\PartNumber;
use App\Services\IdentifierNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartNumberRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PartNumberType::class)],
            'number' => ['required', 'string', 'max:64'],
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
            fn (Validator $validator) => $this->rejectEmptyOnceNormalized($validator),
            fn (Validator $validator) => $this->rejectDuplicate($validator),
        ];
    }

    public function type(): PartNumberType
    {
        return PartNumberType::from($this->validated('type'));
    }

    /**
     * A number of only punctuation normalizes to nothing, which would store a
     * blank identifier that matches every search. `required` cannot see it.
     */
    private function rejectEmptyOnceNormalized(Validator $validator): void
    {
        if ($validator->errors()->has('number')) {
            return;
        }

        if (IdentifierNormalizer::normalize($this->input('number')) === '') {
            $validator->errors()->add('number', __('parts.numbers.validation.not_a_number'));
        }
    }

    /**
     * The unique index would raise a QueryException; this turns the same rule
     * into a field error the form can show.
     */
    private function rejectDuplicate(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $exists = PartNumber::query()
            ->where('part_id', $this->route('part'))
            ->where('type', $this->input('type'))
            ->normalizedAs((string) $this->input('number'))
            ->exists();

        if ($exists) {
            $validator->errors()->add('number', __('parts.numbers.validation.duplicate'));
        }
    }
}
