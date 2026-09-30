<?php

namespace App\Http\Requests\Estimates;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordEstimateDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Estimate::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([EstimateStatus::Approved->value, EstimateStatus::Declined->value])],
            'decision_method' => ['required', Rule::enum(DecisionMethod::class)],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => __('estimates.decision.decision'),
            'decision_method' => __('estimates.decision.method'),
            'decision_note' => __('estimates.decision.note'),
        ];
    }

    public function decision(): EstimateStatus
    {
        return EstimateStatus::from($this->validated('decision'));
    }

    public function decisionMethod(): DecisionMethod
    {
        return DecisionMethod::from($this->validated('decision_method'));
    }

    public function note(): ?string
    {
        return $this->validated('decision_note');
    }
}
