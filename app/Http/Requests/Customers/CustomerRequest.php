<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Services\PhoneNormalizer;
use App\Services\TaxIdChecksum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', self::phoneIsFree($customer instanceof Customer ? $customer : null)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'digits:9', self::taxIdIsValid()],
            'registration_number' => ['nullable', 'string', 'digits:8', 'required_with:tax_id', 'prohibited_if:tax_id,null'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_id.digits' => __('customers.validation.tax_id_digits'),
            'registration_number.digits' => __('customers.validation.registration_number_digits'),
            'registration_number.required_with' => __('customers.validation.registration_number_required'),
            'registration_number.prohibited_if' => __('customers.validation.registration_number_without_tax_id'),
        ];
    }

    public static function phoneIsFree(?Customer $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $digits = PhoneNormalizer::digits(is_string($value) ? $value : null);

            if ($digits === '') {
                return;
            }

            $owner = Customer::query()
                ->withPhoneDigits($digits)
                ->when($ignore, fn ($query, Customer $customer) => $query->whereKeyNot($customer->getKey()))
                ->first(['id', 'name']);

            if ($owner !== null) {
                $fail(__('customers.validation.phone_taken', ['name' => $owner->name]));
            }
        };
    }

    public static function taxIdIsValid(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match('/^\d{9}\z/', $value) !== 1) {
                return;
            }

            if (! TaxIdChecksum::isValid($value)) {
                $fail(__('customers.validation.tax_id_checksum'));
            }
        };
    }

    protected function prepareForValidation(): void
    {
        $stripped = [];

        foreach (['tax_id', 'registration_number'] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $value = preg_replace('/\s+/u', '', $value) ?? $value;
                $stripped[$key] = $value === '' ? null : $value;
            }
        }

        $this->merge($stripped);
    }
}
