<?php

namespace App\Http\Requests\Appointments;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentIndexRequest extends FormRequest
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
            'view' => ['nullable', 'in:day,week'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ];
    }

    /**
     * @return array{view: string, date: CarbonImmutable, status: string}
     */
    public function filters(): array
    {
        $date = $this->string('date')->toString();

        return [
            'view' => $this->string('view')->toString() ?: 'day',
            'date' => $date !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()
                : CarbonImmutable::today(),
            'status' => $this->string('status')->toString(),
        ];
    }
}
