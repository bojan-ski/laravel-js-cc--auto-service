<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidInvoiceTransition;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceVoidingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function issued(): Invoice
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return $this->service()->issue($job, User::factory()->receptionist()->create());
    }

    private function paid(): Invoice
    {
        return $this->service()->markPaid($this->issued(), PaymentMethod::Card);
    }

    public function test_voiding_an_unpaid_invoice_records_the_status_the_time_and_the_admin(): void
    {
        $invoice = $this->issued();
        $admin = User::factory()->admin()->create();

        $this->service()->void($invoice, $admin);

        $fresh = $invoice->fresh();

        $this->assertSame(InvoiceStatus::Void, $fresh->status);
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame($admin->getKey(), $fresh->voided_by);
    }

    /**
     * Spec 18 makes payment irreversible on purpose, so voiding a settled
     * invoice is the only correction path for a payment recorded in error.
     */
    public function test_a_paid_invoice_can_be_voided(): void
    {
        $invoice = $this->paid();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    public function test_the_returned_instance_reflects_the_void_without_a_refetch(): void
    {
        $invoice = $this->issued();

        $returned = $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertSame(InvoiceStatus::Void, $returned->status);
        $this->assertNotNull($returned->voided_at);
        $this->assertSame($invoice, $returned, 'The caller\'s own instance is refreshed and handed back.');
    }

    public function test_voiding_clears_the_jobs_lock(): void
    {
        $invoice = $this->issued();

        $this->assertNotNull($invoice->job->fresh()->locked_at, 'The fixture must actually be locked.');

        $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertNull($invoice->job->fresh()->locked_at);
    }

    /**
     * The overview calls the result "an editable completed state". Reopening to
     * in_progress is a separate, deliberate act — voiding must not perform it.
     */
    public function test_voiding_leaves_the_jobs_status_completed(): void
    {
        $invoice = $this->issued();

        $this->service()->void($invoice, User::factory()->admin()->create());

        $job = $invoice->job->fresh();

        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertNotNull($job->completed_at);
        $this->assertNull($job->cancelled_at);
    }

    public function test_voiding_changes_no_line_item(): void
    {
        $invoice = $this->issued();
        $before = JobItem::query()->orderBy('id')->get()->toArray();

        $this->assertNotEmpty($before, 'The job must carry lines for this to mean anything.');

        $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertEquals($before, JobItem::query()->orderBy('id')->get()->toArray());
    }

    /**
     * Parts fitted to the car are still fitted. Voiding is a billing correction,
     * not a return, so it must write no movement and move no quantity.
     */
    public function test_voiding_writes_no_stock_movement_and_moves_no_stock(): void
    {
        $job = Job::factory()->completed()->create();
        $part = Part::factory()->create(['quantity_in_stock' => 10, 'unit_price' => 250]);

        JobItem::factory()->forJob($job)->forPart($part)->create(['quantity' => 4, 'user_id' => null]);

        $invoice = $this->service()->issue($job, User::factory()->receptionist()->create());

        $movementsBefore = DB::table('stock_movements')->count();
        $stockBefore = $part->fresh()->quantity_in_stock;

        $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertSame($movementsBefore, DB::table('stock_movements')->count());
        $this->assertSame($stockBefore, $part->fresh()->quantity_in_stock);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function voidableStates(): array
    {
        return ['unpaid' => ['issued'], 'paid' => ['paid']];
    }

    /**
     * Run against a paid invoice as well as an unpaid one, because `paid_at` and
     * `payment_method` are null on an unpaid invoice and would sit in this list
     * proving nothing. A void that cleared either would lose the record of how
     * and when a corrected bill was settled.
     */
    #[DataProvider('voidableStates')]
    public function test_voiding_preserves_the_number_totals_and_issue_details(string $state): void
    {
        $invoice = $this->{$state}();

        $before = $invoice->only([
            'number', 'subtotal', 'tax_rate', 'tax_amount', 'total',
            'issued_at', 'issued_by', 'due_at', 'paid_at', 'payment_method',
        ]);

        if ($state === 'paid') {
            $this->assertNotNull($before['paid_at'], 'The paid fixture must carry a payment time.');
            $this->assertSame(PaymentMethod::Card, $before['payment_method'], 'The paid fixture must carry a method.');
        }

        $this->service()->void($invoice, User::factory()->admin()->create());

        $this->assertEquals($before, $invoice->fresh()->only(array_keys($before)));
    }

    public function test_a_void_invoice_cannot_be_voided_again(): void
    {
        $invoice = $this->issued();
        $admin = User::factory()->admin()->create();

        $this->service()->void($invoice, $admin);

        $voidedAt = $invoice->fresh()->voided_at;
        $voidedBy = $invoice->fresh()->voided_by;

        try {
            $this->service()->void($invoice->fresh(), User::factory()->admin()->create());
            $this->fail('Expected InvalidInvoiceTransition on a second void.');
        } catch (InvalidInvoiceTransition $exception) {
            $this->assertStringContainsString('unpaid or paid', $exception->getMessage());
        }

        $fresh = $invoice->fresh();

        $this->assertTrue($voidedAt->equalTo($fresh->voided_at), 'The original void details must survive.');
        $this->assertSame($voidedBy, $fresh->voided_by);
    }

    /**
     * Provoked by a real database failure rather than a stubbed model event, as
     * StockServiceTest established: the invoice write is made to fail by pointing
     * `voided_by` at a user the foreign key can no longer find.
     *
     * Only this direction can be provoked honestly. The other — a failure at the
     * job write — has no real database error available to it, because clearing a
     * nullable datetime column cannot fail. That direction is carried by the
     * single transaction both writes share, which this test proves rolls back.
     */
    public function test_a_failed_invoice_write_leaves_the_job_locked(): void
    {
        $invoice = $this->issued();
        $lockedAt = $invoice->job->fresh()->locked_at;

        $ghost = User::factory()->admin()->create();
        DB::table('users')->where('id', $ghost->getKey())->delete();

        try {
            $this->service()->void($invoice, $ghost);
            $this->fail('Voiding for a deleted user should violate the foreign key.');
        } catch (QueryException) {
            // expected
        }

        $fresh = $invoice->fresh();

        $this->assertSame(InvoiceStatus::Unpaid, $fresh->status, 'The invoice must not be voided.');
        $this->assertNull($fresh->voided_at);
        $this->assertNull($fresh->voided_by);

        $after = $invoice->job->fresh()->locked_at;

        $this->assertNotNull($after, 'A failed void must leave the job locked.');
        $this->assertTrue($lockedAt->equalTo($after));
    }

    public function test_voiding_touches_no_other_invoice(): void
    {
        $target = $this->issued();
        $bystander = $this->issued();

        $this->service()->void($target, User::factory()->admin()->create());

        $this->assertSame(InvoiceStatus::Unpaid, $bystander->fresh()->status);
        $this->assertNotNull($bystander->job->fresh()->locked_at);
    }
}
