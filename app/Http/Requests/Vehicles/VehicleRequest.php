<?php

namespace App\Http\Requests\Vehicles;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

abstract class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The vehicle this request edits, or null when it creates one.
     */
    abstract protected function vehicle(): ?Vehicle;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_plate' => ['required', 'string', 'max:20', $this->uniqueAmongActive('registration_plate')],
            'make' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'vin' => ['nullable', 'string', 'max:17', $this->uniqueAmongActive('vin')],
            'engine' => ['nullable', 'string', 'max:60'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectDeletedConflict($validator, 'registration_plate', 'plate_deleted'),
            fn (Validator $validator) => $this->rejectDeletedConflict($validator, 'vin', 'vin_deleted'),
        ];
    }

    /**
     * The unique rule ignores soft-deleted rows so that they can be reported
     * separately, with a message that says where the plate actually went.
     */
    private function uniqueAmongActive(string $column): Unique
    {
        $rule = Rule::unique('vehicles', $column)->withoutTrashed();

        return $this->vehicle() ? $rule->ignore($this->vehicle()) : $rule;
    }

    private function rejectDeletedConflict(Validator $validator, string $column, string $key): void
    {
        if ($validator->errors()->has($column) || blank($this->input($column))) {
            return;
        }

        $message = self::deletedConflictMessage($column, $key, $this->input($column));

        if ($message !== null) {
            $validator->errors()->add($column, $message);
        }
    }

    public static function deletedConflictMessage(string $column, string $key, mixed $value): ?string
    {
        $conflict = Vehicle::onlyTrashed()
            ->where($column, $value)
            ->with(['customer' => fn ($query) => $query->withTrashed()])
            ->first();

        return $conflict === null ? null : __('vehicles.validation.'.$key, [
            'owner' => $conflict->customer->name,
        ]);
    }
}
