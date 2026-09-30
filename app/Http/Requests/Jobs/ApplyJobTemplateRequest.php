<?php

namespace App\Http\Requests\Jobs;

use App\Models\JobTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApplyJobTemplateRequest extends FormRequest
{
    private ?JobTemplate $template = null;

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
            'job_template_id' => ['required', 'integer', Rule::exists('job_templates', 'id')->whereNull('deleted_at')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
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
            fn (Validator $validator) => $this->checkTemplate($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'job_template_id' => __('templates.apply.template'),
            'user_id' => __('templates.apply.mechanic'),
        ];
    }

    public function template(): JobTemplate
    {
        return $this->template ??= JobTemplate::query()->findOrFail($this->integer('job_template_id'));
    }

    public function mechanicId(): ?int
    {
        return $this->filled('user_id') ? $this->integer('user_id') : null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isFrontOffice()) {
            $this->merge(['user_id' => $this->user()->getKey()]);
        }
    }

    private function checkTemplate(Validator $validator): void
    {
        if ($validator->errors()->has('job_template_id')) {
            return;
        }

        if (! $this->template()->hasItems()) {
            $validator->errors()->add('job_template_id', __('templates.apply.errors.no_lines'));

            return;
        }

        if ($this->user()->isFrontOffice() && ! $this->filled('user_id') && $this->template()->hasLaborItems()) {
            $validator->errors()->add('user_id', __('templates.apply.errors.mechanic_required'));
        }
    }
}
