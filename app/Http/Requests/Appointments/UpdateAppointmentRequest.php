<?php

namespace App\Http\Requests\Appointments;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAppointmentRequest extends AppointmentScheduleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->scheduleRules();
    }

    protected function changesSchedule(): bool
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');
        $submitted = strtotime((string) $this->input('scheduled_at'));

        return $submitted === false
            || $submitted !== $appointment->scheduled_at->getTimestamp()
            || $this->integer('duration_minutes') !== $appointment->duration_minutes;
    }
}
