<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

class AdminLockoutPrevented extends RuntimeException
{
    public function __construct(
        public readonly User $user,
        string $because,
    ) {
        parent::__construct(
            "Refused the operation on user {$user->getKey()}: {$because}."
        );
    }
}
