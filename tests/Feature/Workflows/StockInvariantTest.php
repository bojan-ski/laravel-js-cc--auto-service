<?php

namespace Tests\Feature\Workflows;

use App\Enums\JobStatus;
use App\Enums\StockMovementReason;
use App\Models\Appointment;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AssertsStockInvariant;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;

/**
 * Spec 15's invariant — every part holds exactly the sum of its movements — is
 * asserted per operation by each spec that moves stock. This is the one place
 * it is asserted across a whole job's life, crossing all three state machines:
 * arrival, line items, invoice, void, reopen, edit and cancellation.
 *
 * The ledger check alone cannot see a step that skips BOTH the quantity write
 * and the movement (the two sides stay equal), so every step also pins the
 * balance it expects and the movement count it adds. Opening stock is built
 * through StockService::record(), never PartFactory::inStock(), because the
 * factory sets a quantity with no movement behind it and the ledger check is
 * absolute across the whole parts table.
 */
class StockInvariantTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_stock_equals_its_ledger_at_every_step_of_a_jobs_whole_life(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $admin = User::factory()->admin()->create();
        $stock = app(StockService::class);
        $items = app(JobItemService::class);
        $invoices = app(InvoiceService::class);

        $pads = Part::factory()->create();
        $fluid = Part::factory()->create();
        $stock->record($pads, 10, StockMovementReason::Restock, $receptionist);
        $stock->record($fluid, 6, StockMovementReason::Restock, $receptionist);
        $this->assertBalances($pads, 10, $fluid, 6, movements: 2);

        $job = $this->service()->createFromAppointment(
            Appointment::factory()->booked()->create(),
            $receptionist,
            ['complaint' => 'Pulls right under braking'],
        );
        $this->assertBalances($pads, 10, $fluid, 6, movements: 2);

        $this->service()->start($job->fresh());
        $padsLine = $items->addPart($job->fresh(), $pads, $receptionist, ['quantity' => 2]);
        $items->addPart($job->fresh(), $fluid, $receptionist, ['quantity' => 3]);
        $this->assertBalances($pads, 8, $fluid, 3, movements: 4);

        $this->service()->complete($job->fresh());
        $invoice = $invoices->issue($job->fresh(), $receptionist);
        $this->assertNotNull($job->fresh()->locked_at, 'Issuing locks the job.');
        $this->assertBalances($pads, 8, $fluid, 3, movements: 4);

        $invoices->void($invoice, $admin);
        $this->assertNull($job->fresh()->locked_at, 'Voiding unlocks the job.');
        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertBalances($pads, 8, $fluid, 3, movements: 4);

        $this->service()->reopen($job->fresh());
        $items->update($padsLine->fresh(), $receptionist, [
            'description' => $padsLine->description,
            'quantity' => '5',
        ]);
        $this->assertBalances($pads, 5, $fluid, 3, movements: 5);

        $this->service()->cancel($job->fresh(), $receptionist);
        $this->assertSame(JobStatus::Cancelled, $job->fresh()->status);
        $this->assertBalances($pads, 10, $fluid, 6, movements: 7);
        $this->assertSame(2, $job->fresh()->items()->count(), 'Cancelling leaves the lines in place.');

        $this->assertSame(
            ['job_return' => 2, 'job_use' => 3, 'restock' => 2],
            DB::table('stock_movements')
                ->selectRaw('reason, COUNT(*) AS aggregate')
                ->groupBy('reason')
                ->orderBy('reason')
                ->pluck('aggregate', 'reason')
                ->map(fn ($count): int => (int) $count)
                ->all(),
        );
    }

    private function assertBalances(Part $pads, int $padsExpected, Part $fluid, int $fluidExpected, int $movements): void
    {
        $this->assertSame($padsExpected, $pads->fresh()->quantity_in_stock);
        $this->assertSame($fluidExpected, $fluid->fresh()->quantity_in_stock);
        $this->assertSame($movements, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }
}
