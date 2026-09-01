<?php

namespace App\Http\Requests\Invoices;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceIndexRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{status: string, search: string}
     */
    public function filters(): array
    {
        return [
            'status' => $this->string('status')->toString(),
            'search' => $this->string('search')->trim()->toString(),
        ];
    }
}
