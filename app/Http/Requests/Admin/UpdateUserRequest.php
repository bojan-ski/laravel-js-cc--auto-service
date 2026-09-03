<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends UserRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->withoutTrashed()->ignore($this->target()),
            ],
            'role' => $this->isSelf()
                ? ['prohibited']
                : ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function role(): ?UserRole
    {
        $role = $this->string('role')->toString();

        return $role === '' ? null : UserRole::from($role);
    }

    public function isSelf(): bool
    {
        return $this->target()->is($this->user());
    }

    private function target(): User
    {
        return $this->route('user');
    }
}
