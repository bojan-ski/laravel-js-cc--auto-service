<?php

namespace App\Http\Requests\Parts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartIndexRequest extends FormRequest
{
    public const string LOW_STOCK = 'low_stock';

    public const string TRASHED_WITH = 'with';

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
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', Rule::in([self::LOW_STOCK])],
            'trashed' => ['nullable', Rule::in([self::TRASHED_WITH])],
        ];
    }

    /**
     * @return array{search: string, filter: string, trashed: string}
     */
    public function filters(): array
    {
        return [
            'search' => $this->string('search')->trim()->toString(),
            'filter' => $this->string('filter')->toString(),
            'trashed' => $this->string('trashed')->toString(),
        ];
    }
}
