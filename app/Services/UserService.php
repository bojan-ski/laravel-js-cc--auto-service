<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    /**
     * @return array<int, string>
     */
    public function pickerOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
