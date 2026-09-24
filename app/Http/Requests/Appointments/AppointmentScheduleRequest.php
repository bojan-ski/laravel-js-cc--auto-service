<?php

namespace App\Http\Requests\Appointments;

use App\Services\ShopSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class AppointmentScheduleRequest extends FormRequest
{
    abstract protected function changesSchedule(): bool;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function scheduleRules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', Rule::when($this->changesSchedule(), ['after_or_equal:tomorrow'])],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.after_or_equal' => __('appointments.validation.not_before_tomorrow'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(ShopSettingService $settings): array
    {
        return [
            function (Validator $validator) use ($settings): void {
                if (! $this->changesSchedule() || $validator->errors()->hasAny(['scheduled_at', 'duration_minutes'])) {
                    return;
                }

                $hours = $settings->current();

                if (! $hours->allowsAppointment(Carbon::parse($this->input('scheduled_at')), $this->integer('duration_minutes'))) {
                    $validator->errors()->add('scheduled_at', __('appointments.validation.outside_hours', [
                        'open' => $hours->opensAt(),
                        'close' => $hours->closesAt(),
                    ]));
                }
            },
        ];
    }
}
