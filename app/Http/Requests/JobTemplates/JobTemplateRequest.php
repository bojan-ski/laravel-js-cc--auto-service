<?php

namespace App\Http\Requests\JobTemplates;

use App\Models\JobTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JobTemplateRequest extends FormRequest
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
        $name = Rule::unique('job_templates', 'name')->withoutTrashed();

        if ($this->route('jobTemplate') !== null) {
            $name->ignore($this->route('jobTemplate'));
        }

        return [
            'name' => ['required', 'string', 'max:255', $name],
            'description' => ['nullable', 'string', 'max:2000'],
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
            fn (Validator $validator) => $this->rejectTrashedConflict($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('templates.fields.name'),
            'description' => __('templates.fields.description'),
        ];
    }

    private function rejectTrashedConflict(Validator $validator): void
    {
        if ($validator->errors()->has('name') || blank($this->input('name'))) {
            return;
        }

        $conflict = JobTemplate::onlyTrashed()->where('name', $this->input('name'))->first(['id', 'name']);

        if ($conflict === null) {
            return;
        }

        $validator->errors()->add('name', __('templates.errors.name_trashed', ['name' => $conflict->name]));
    }
}
