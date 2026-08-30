<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * The invariant only holds for stock built through StockService::record().
 * StockMovementFactory writes rows straight to the table without moving
 * `parts.quantity_in_stock`, so a fixture made with the factory will fail this
 * assertion by design — that is not a defect in the service.
 */
trait AssertsStockInvariant
{
    protected function assertStockMatchesLedger(): void
    {
        $parts = DB::table('parts')->pluck('quantity_in_stock', 'id');

        $this->assertNotEmpty($parts, 'No part exists, so the invariant would hold vacuously.');

        $ledger = DB::table('stock_movements')
            ->selectRaw('part_id, SUM(quantity_change) AS total')
            ->groupBy('part_id')
            ->pluck('total', 'part_id');

        foreach ($parts as $id => $stock) {
            $this->assertSame(
                (int) ($ledger[$id] ?? 0),
                (int) $stock,
                "Part {$id} holds {$stock} but its movements sum to ".(int) ($ledger[$id] ?? 0).'.',
            );
        }
    }
}
