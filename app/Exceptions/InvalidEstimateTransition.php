<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class InvalidEstimateTransition extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly Model $record,
        public readonly array $changes,
        string $because,
    ) {
        $columns = implode(', ', array_keys($changes));
        $table = $record->getTable();

        parent::__construct(
            "Refused a write to {$table} {$record->getKey()} [{$columns}]: {$because}."
        );
    }
}
