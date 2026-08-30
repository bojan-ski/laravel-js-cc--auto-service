<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

class InvalidInvoiceTransition extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $changes,
        string $because,
    ) {
        $columns = implode(', ', array_keys($changes));

        parent::__construct(
            "Refused a write to invoice {$invoice->getKey()} [{$columns}]: {$because}."
        );
    }
}
