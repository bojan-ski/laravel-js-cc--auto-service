<?php

namespace App\Http\Requests\Jobs;

use App\Models\Appointment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    private ?Appointment $appointment = null;

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
            'appointment_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id'),
                'prohibits:vehicle_id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Appointment::find($value)?->canBeMarkedArrived()) {
                        $fail(__('jobs.validation.appointment_not_booked'));
                    }
                },
            ],
            'vehicle_id' => [
                'required_without:appointment_id',
                'integer',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            'complaint' => ['required', 'string', 'max:2000'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function appointment(): ?Appointment
    {
        if (! $this->filled('appointment_id')) {
            return null;
        }

        return $this->appointment ??= Appointment::query()->findOrFail($this->integer('appointment_id'));
    }
}
