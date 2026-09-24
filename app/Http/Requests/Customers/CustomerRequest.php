<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Services\PhoneNormalizer;
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
            'notes' => ['nullable', 'string', 'max:2000'],
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
}
