<?php

namespace App\Http\Requests\Appointments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends AppointmentScheduleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->whereNull('deleted_at'),
            ],
            ...$this->scheduleRules(),
        ];
    }

    protected function changesSchedule(): bool
    {
        return true;
    }
}
