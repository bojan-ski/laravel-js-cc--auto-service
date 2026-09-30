<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class UnreadableSignature extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct("The drawn signature could not be read: {$reason}.", 0, $previous);
    }
}
