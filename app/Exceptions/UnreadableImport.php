<?php

namespace App\Exceptions;

use RuntimeException;

class UnreadableImport extends RuntimeException
{
    public function __construct(string $because)
    {
        parent::__construct("The import file could not be read: {$because}.");
    }
}
