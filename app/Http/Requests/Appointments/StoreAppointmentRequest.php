<?php

namespace App\Http\Requests\Appointments;

use App\Http\Requests\Customers\ValidatesCustomerIntake;
use App\Services\ShopSettingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends AppointmentScheduleRequest
{
    use ValidatesCustomerIntake;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->intakeRules(),
            ...$this->scheduleRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), ...$this->intakeMessages()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->intakeAttributes();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(ShopSettingService $settings): array
    {
        return [...parent::after($settings), ...$this->intakeAfter()];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointmentAttributes(): array
    {
        return $this->safe()->only(['scheduled_at', 'duration_minutes', 'notes']);
    }

    protected function changesSchedule(): bool
    {
        return true;
    }
}
