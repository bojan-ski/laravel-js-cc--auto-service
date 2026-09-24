<?php

namespace App\Http\Requests\Jobs;

use App\Http\Requests\Customers\ValidatesCustomerIntake;
use App\Models\Appointment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobRequest extends FormRequest
{
    use ValidatesCustomerIntake;

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
        $intakeFields = implode(',', ['customer_id', 'vehicle_id', ...self::NEW_CUSTOMER_FIELDS, ...self::NEW_VEHICLE_FIELDS]);

        return [
            'appointment_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id'),
                "prohibits:{$intakeFields}",
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Appointment::find($value)?->canBeMarkedArrived()) {
                        $fail(__('jobs.validation.appointment_not_booked'));
                    }
                },
            ],
            ...$this->intakeRules(['appointment_id']),
            'complaint' => ['required', 'string', 'max:2000'],
            'mileage_in' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->intakeMessages();
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
    public function after(): array
    {
        return $this->intakeAfter();
    }

    /**
     * @return array<string, mixed>
     */
    public function jobAttributes(): array
    {
        return $this->safe()->only(['complaint', 'mileage_in']);
    }

    public function appointment(): ?Appointment
    {
        if (! $this->filled('appointment_id')) {
            return null;
        }

        return $this->appointment ??= Appointment::query()->findOrFail($this->integer('appointment_id'));
    }
}
