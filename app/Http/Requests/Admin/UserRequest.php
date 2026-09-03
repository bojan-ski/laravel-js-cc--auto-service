<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectOffboardedConflict($validator),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    private function rejectOffboardedConflict(Validator $validator): void
    {
        if ($validator->errors()->has('email') || blank($this->input('email'))) {
            return;
        }

        $conflict = User::onlyTrashed()->where('email', $this->input('email'))->first();

        if ($conflict === null) {
            return;
        }

        $validator->errors()->add('email', __('staff.validation.email_offboarded', [
            'name' => $conflict->name,
        ]));
    }
}
