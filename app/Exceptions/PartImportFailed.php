<?php

namespace App\Exceptions;

use RuntimeException;

class PartImportFailed extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct('The import file was rejected with '.count($errors).' error(s).');
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
