<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\Vehicles\VehicleRequest;
use App\Models\Vehicle;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCustomerIntake
{
    public const array NEW_CUSTOMER_FIELDS = ['new_customer_name', 'new_customer_phone', 'new_customer_email'];

    public const array NEW_VEHICLE_FIELDS = ['new_vehicle_plate', 'new_vehicle_make', 'new_vehicle_model', 'new_vehicle_year'];

    /**
     * @param  list<string>  $exemptWhen  fields whose presence makes the whole intake unnecessary
     * @return array<string, array<mixed>>
     */
    protected function intakeRules(array $exemptWhen = []): array
    {
        $noCustomer = implode(',', ['customer_id', 'vehicle_id', ...$exemptWhen]);
        $noVehicle = implode(',', ['vehicle_id', ...$exemptWhen]);
        $newCustomer = implode(',', self::NEW_CUSTOMER_FIELDS);
        $newVehicle = implode(',', self::NEW_VEHICLE_FIELDS);

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at'), "prohibits:{$newCustomer}"],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->whereNull('deleted_at'), "prohibits:{$newCustomer},{$newVehicle}"],
            'new_customer_name' => ["required_without_all:{$noCustomer}", 'nullable', 'string', 'max:255'],
            'new_customer_phone' => ["required_without_all:{$noCustomer}", 'nullable', 'string', 'max:40', CustomerRequest::phoneIsFree()],
            'new_customer_email' => ['nullable', 'email', 'max:255'],
            'new_vehicle_plate' => ["required_without_all:{$noVehicle}", 'nullable', 'string', 'max:20', self::plateIsFree()],
            'new_vehicle_make' => ["required_without_all:{$noVehicle}", 'nullable', 'string', 'max:60'],
            'new_vehicle_model' => ["required_without_all:{$noVehicle}", 'nullable', 'string', 'max:60'],
            'new_vehicle_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function intakeMessages(): array
    {
        return [
            'new_customer_name.required_without_all' => __('customers.intake.required_customer'),
            'new_customer_phone.required_without_all' => __('customers.intake.required_customer'),
            'new_vehicle_plate.required_without_all' => __('customers.intake.required_vehicle'),
            'new_vehicle_make.required_without_all' => __('customers.intake.required_vehicle'),
            'new_vehicle_model.required_without_all' => __('customers.intake.required_vehicle'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function intakeAttributes(): array
    {
        return [
            'customer_id' => __('customers.intake.customer'),
            'vehicle_id' => __('customers.intake.vehicle'),
            ...__('customers.intake.attributes'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    protected function intakeAfter(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['customer_id', 'vehicle_id']) || ! $this->filled(['customer_id', 'vehicle_id'])) {
                    return;
                }

                $owner = Vehicle::query()->whereKey($this->integer('vehicle_id'))->value('customer_id');

                if ($owner !== $this->integer('customer_id')) {
                    $validator->errors()->add('vehicle_id', __('customers.intake.vehicle_not_theirs'));
                }
            },
        ];
    }

    /**
     * @return array{
     *     customer_id: int|null,
     *     vehicle_id: int|null,
     *     customer: array{name: string|null, phone: string|null, email: string|null},
     *     vehicle: array{registration_plate: string|null, make: string|null, model: string|null, year: int|null},
     * }
     */
    public function intake(): array
    {
        return [
            'customer_id' => $this->filled('customer_id') ? $this->integer('customer_id') : null,
            'vehicle_id' => $this->filled('vehicle_id') ? $this->integer('vehicle_id') : null,
            'customer' => [
                'name' => $this->validated('new_customer_name'),
                'phone' => $this->validated('new_customer_phone'),
                'email' => $this->validated('new_customer_email'),
            ],
            'vehicle' => [
                'registration_plate' => $this->validated('new_vehicle_plate'),
                'make' => $this->validated('new_vehicle_make'),
                'model' => $this->validated('new_vehicle_model'),
                'year' => $this->filled('new_vehicle_year') ? $this->integer('new_vehicle_year') : null,
            ],
        ];
    }

    private static function plateIsFree(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $taken = Vehicle::query()
                ->where('registration_plate', $value)
                ->with(['customer' => fn ($query) => $query->withTrashed()])
                ->first();

            $message = $taken !== null
                ? __('customers.intake.plate_taken', ['plate' => $taken->registration_plate, 'owner' => $taken->customer->name])
                : VehicleRequest::deletedConflictMessage('registration_plate', 'plate_deleted', $value);

            if ($message !== null) {
                $fail($message);
            }
        };
    }
}
