<?php

namespace App\Http\Requests\JobTemplates;

use App\Enums\JobItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class JobTemplateItemRequest extends FormRequest
{
    abstract public function type(): ?JobItemType;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{description: ?string, quantity: string, part_id: ?int}
     */
    public function lineAttributes(): array
    {
        $validated = $this->validated();

        return [
            'description' => $validated['line_description'] ?? null,
            'quantity' => (string) $validated['quantity'],
            'part_id' => isset($validated['part_id']) ? (int) $validated['part_id'] : null,
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function rulesFor(JobItemType $type): array
    {
        return $type === JobItemType::Labor
            ? [
                'line_description' => ['required', 'string', 'max:255'],
                'part_id' => ['prohibited'],
                'quantity' => JobItemType::Labor->quantityRules(),
            ]
            : [
                'line_description' => ['prohibited'],
                'part_id' => ['required', 'integer', Rule::exists('parts', 'id')->whereNull('deleted_at')],
                'quantity' => JobItemType::Part->quantityRules(),
            ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'line_description' => __('templates.lines.add.description'),
            'part_id' => __('templates.lines.add.part'),
            'quantity' => $this->type() === JobItemType::Part
                ? __('templates.lines.add.units')
                : __('templates.lines.add.hours'),
        ];
    }
}
