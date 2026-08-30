<?php

namespace App\Http\Requests\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('due_at') === '') {
            $this->merge(['due_at' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function dueAt(): ?CarbonInterface
    {
        return $this->filled('due_at') ? $this->date('due_at')->startOfDay() : null;
    }
}
