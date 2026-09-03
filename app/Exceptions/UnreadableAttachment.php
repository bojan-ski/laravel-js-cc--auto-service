<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class UnreadableAttachment extends RuntimeException
{
    public function __construct(
        public readonly string $originalFilename,
        public readonly string $mimeType,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Attachment {$originalFilename} claims to be {$mimeType} but could not be decoded as an image.",
            0,
            $previous,
        );
    }
}
