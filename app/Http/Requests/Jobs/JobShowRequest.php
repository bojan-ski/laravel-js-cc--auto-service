<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JobShowRequest extends FormRequest
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
            'edit' => ['nullable', 'integer'],
        ];
    }

    public function editingItemId(): ?int
    {
        return $this->filled('edit') ? $this->integer('edit') : null;
    }

    public function addingType(): string
    {
        return $this->query('add') === 'part' ? 'part' : 'labor';
    }
}
