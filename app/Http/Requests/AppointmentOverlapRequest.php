<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentOverlapRequest extends FormRequest
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
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'exclude' => ['nullable', 'integer', Rule::exists('appointments', 'id')],
        ];
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('scheduled_at')->toString());
    }

    public function durationMinutes(): int
    {
        return $this->integer('duration_minutes');
    }

    public function excludeId(): ?int
    {
        return $this->has('exclude') && $this->string('exclude')->isNotEmpty()
            ? $this->integer('exclude')
            : null;
    }
}
