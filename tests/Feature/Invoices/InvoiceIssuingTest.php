<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Exceptions\InvoiceNotIssuable;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InvoiceIssuingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function invoiceableJob(): Job
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create([
            'quantity' => 1.50,
            'unit_price' => 3000,
        ]);

        return $job;
    }

    public function test_a_completed_job_with_a_line_item_is_invoiced(): void
    {
        $job = $this->invoiceableJob();
        $issuer = User::factory()->receptionist()->create();

        $invoice = $this->service()->issue($job, $issuer);

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNotNull($invoice->issued_at);
        $this->assertSame($issuer->getKey(), $invoice->issued_by);
        $this->assertNull($invoice->paid_at);
        $this->assertNull($invoice->voided_at);
        $this->assertNull($invoice->voided_by);
        $this->assertNull($invoice->due_at);
        $this->assertTrue($invoice->job->is($job));
    }

    public function test_the_job_is_locked_in_the_same_transaction(): void
    {
        $job = $this->invoiceableJob();

        $this->assertNull($job->locked_at);

        $this->service()->issue($job, User::factory()->receptionist()->create());

        $this->assertNotNull($job->fresh()->locked_at);
        $this->assertNotNull($job->locked_at, 'The passed instance should reflect the lock.');
    }

    public function test_the_subtotal_covers_labor_and_part_lines(): void
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1.50, 'unit_price' => 3000]);

        $part = Part::factory()->create(['unit_price' => 250]);
        JobItem::factory()->forJob($job)->forPart($part)->create(['quantity' => 2, 'user_id' => null]);

        $invoice = $this->service()->issue($job, User::factory()->receptionist()->create());

        $this->assertSame('5000.00', $invoice->subtotal);
        $this->assertSame('1000.00', $invoice->tax_amount);
        $this->assertSame('6000.00', $invoice->total);
    }

    public function test_the_tax_rate_is_stamped_from_config(): void
    {
        config(['shop.vat_rate' => 10]);

        $invoice = $this->service()->issue(
            $this->invoiceableJob(),
            User::factory()->receptionist()->create(),
        );

        $this->assertSame('10.00', $invoice->tax_rate);
        $this->assertSame('450.00', $invoice->tax_amount);
        $this->assertSame('4950.00', $invoice->total);
    }

    public function test_changing_the_config_rate_afterwards_does_not_alter_an_issued_invoice(): void
    {
        $invoice = $this->service()->issue(
            $this->invoiceableJob(),
            User::factory()->receptionist()->create(),
        );

        config(['shop.vat_rate' => 99]);

        $this->assertSame('20.00', $invoice->fresh()->tax_rate);
        $this->assertSame('900.00', $invoice->fresh()->tax_amount);
    }

    public function test_a_due_date_is_stored_when_supplied(): void
    {
        $due = now()->addWeek()->startOfDay();

        $invoice = $this->service()->issue(
            $this->invoiceableJob(),
            User::factory()->receptionist()->create(),
            $due,
        );

        $this->assertInstanceOf(CarbonInterface::class, $invoice->fresh()->due_at);
        $this->assertTrue($invoice->fresh()->due_at->equalTo($due));
    }

    /**
     * @return array<string, array{JobStatus}>
     */
    public static function nonCompletedStatuses(): array
    {
        return [
            'received' => [JobStatus::Received],
            'in progress' => [JobStatus::InProgress],
            'awaiting parts' => [JobStatus::AwaitingParts],
            'cancelled' => [JobStatus::Cancelled],
        ];
    }

    #[DataProvider('nonCompletedStatuses')]
    public function test_a_job_that_is_not_completed_cannot_be_invoiced(JobStatus $status): void
    {
        $job = Job::factory()->create(['status' => $status]);
        JobItem::factory()->forJob($job)->create();

        $this->assertRefused($job, InvoiceNotIssuable::NOT_COMPLETED);
    }

    public function test_a_completed_job_with_no_line_items_cannot_be_invoiced(): void
    {
        $this->assertRefused(Job::factory()->completed()->create(), InvoiceNotIssuable::NO_LINE_ITEMS);
    }

    public function test_a_locked_job_cannot_be_invoiced(): void
    {
        $job = $this->invoiceableJob();
        $job->locked_at = now()->subMinutes(30);
        $job->save();

        $this->assertRefused($job, InvoiceNotIssuable::LOCKED);
    }

    public function test_a_job_already_holding_a_non_void_invoice_cannot_be_invoiced(): void
    {
        $job = $this->invoiceableJob();
        Invoice::factory()->unpaid()->forJob($job)->create();

        $this->assertRefused($job, InvoiceNotIssuable::ALREADY_INVOICED);
    }

    public function test_a_job_holding_a_paid_invoice_cannot_be_invoiced_again(): void
    {
        $job = $this->invoiceableJob();
        Invoice::factory()->paid()->forJob($job)->create();

        $this->assertRefused($job, InvoiceNotIssuable::ALREADY_INVOICED);
    }

    public function test_a_job_holding_only_void_invoices_can_be_invoiced_again(): void
    {
        $job = $this->invoiceableJob();
        Invoice::factory()->void()->forJob($job)->create();
        Invoice::factory()->void()->forJob($job)->create();

        $invoice = $this->service()->issue($job, User::factory()->receptionist()->create());

        $this->assertTrue($invoice->isUnpaid());
        $this->assertSame(3, $job->invoices()->count());
        $this->assertSame(1, $job->invoices()->where('status', '!=', InvoiceStatus::Void->value)->count());
    }

    public function test_the_job_is_not_locked_when_the_invoice_insert_fails(): void
    {
        $job = $this->invoiceableJob();
        $taken = 'INV-'.now()->format('Y').'-0001';
        Invoice::factory()->void()->forJob($job)->create(['number' => $taken]);

        $generator = new CollidingNumberGenerator($taken, collidingCalls: PHP_INT_MAX);
        $this->app->instance(InvoiceNumberGenerator::class, $generator);

        try {
            $this->service()->issue($job, User::factory()->receptionist()->create());
            $this->fail('Expected UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException) {
            // The literal, not the constant: asserting against NUMBER_ATTEMPTS
            // would pass for any bound, including one.
            $this->assertSame(3, $generator->calls, 'The retry gives up after three attempts.');
            $this->assertSame(3, InvoiceService::NUMBER_ATTEMPTS);
        }

        $this->assertNull($job->fresh()->locked_at);
        $this->assertSame(1, $job->invoices()->count());
    }

    public function test_a_number_collision_is_recovered_by_regenerating(): void
    {
        $job = $this->invoiceableJob();
        $taken = 'INV-'.now()->format('Y').'-0001';
        Invoice::factory()->void()->forJob($job)->create(['number' => $taken]);

        $generator = new CollidingNumberGenerator($taken, collidingCalls: 1);
        $this->app->instance(InvoiceNumberGenerator::class, $generator);

        $invoice = $this->service()->issue($job, User::factory()->receptionist()->create());

        $this->assertSame(2, $generator->calls, 'One collision, then one fresh allocation.');
        $this->assertNotSame($taken, $invoice->number);
        $this->assertSame('INV-'.now()->format('Y').'-0002', $invoice->number);
        $this->assertNotNull($job->fresh()->locked_at);
        $this->assertSame(2, $job->invoices()->count());
    }

    /**
     * The lock write is the last statement in the transaction, and no database
     * constraint can make it fail on demand. A temporary `saving` listener
     * supplies the failure instead — additive, so the model's own lock guard is
     * left intact (unlike flushing the listeners, which would remove it).
     */
    public function test_no_invoice_remains_when_the_lock_write_fails(): void
    {
        $job = $this->invoiceableJob();

        Job::saving(function (Job $saving): void {
            if ($saving->isDirty('locked_at')) {
                throw new RuntimeException('lock write failed');
            }
        });

        try {
            $this->service()->issue($job, User::factory()->receptionist()->create());
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertSame('lock write failed', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::query()->count());
        $this->assertNull($job->fresh()->locked_at);
    }

    private function assertRefused(Job $job, string $precondition): void
    {
        $before = Invoice::query()->count();
        $lockedAtBefore = $job->fresh()->locked_at;

        try {
            $this->service()->issue($job, User::factory()->receptionist()->create());
            $this->fail("Expected InvoiceNotIssuable [{$precondition}].");
        } catch (InvoiceNotIssuable $exception) {
            $this->assertSame($precondition, $exception->precondition);
            $this->assertTrue($exception->job->is($job));
        }

        $this->assertSame($before, Invoice::query()->count(), 'A refusal must write no invoice.');
        $this->assertEquals($lockedAtBefore, $job->fresh()->locked_at, 'A refusal must not change the lock.');
    }
}

class CollidingNumberGenerator extends InvoiceNumberGenerator
{
    public int $calls = 0;

    public function __construct(
        private readonly string $taken,
        private readonly int $collidingCalls,
    ) {}

    public function generate(CarbonInterface $on): string
    {
        $this->calls++;

        return $this->calls <= $this->collidingCalls ? $this->taken : parent::generate($on);
    }
}
