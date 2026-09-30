<?php

namespace App\Exceptions;

use RuntimeException;

class JobTemplateHasDeletedPart extends RuntimeException
{
    public function __construct(
        public readonly string $partName,
    ) {
        parent::__construct("The template uses the deleted part {$partName}.");
    }
}
