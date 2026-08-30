<?php

namespace App\Exceptions;

use App\Enums\StockMovementReason;
use App\Models\Part;
use RuntimeException;

class InvalidStockMovement extends RuntimeException
{
    public function __construct(
        public readonly Part $part,
        public readonly int $quantityChange,
        public readonly StockMovementReason $reason,
        string $because,
    ) {
        parent::__construct(
            "Refused a {$reason->value} movement of {$quantityChange} on part {$part->getKey()}: {$because}."
        );
    }
}
