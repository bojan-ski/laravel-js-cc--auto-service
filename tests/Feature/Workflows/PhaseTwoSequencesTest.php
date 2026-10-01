<?php

namespace Tests\Feature\Workflows;

use App\Enums\AttachmentCategory;
use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Enums\FuelLevel;
use App\Enums\InvoiceStatus;
use App\Enums\JobItemType;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\SignatureMethod;
use App\Enums\StockMovementReason;
use App\Exceptions\CheckInNotOpen;
use App\Exceptions\EstimateNotCreatable;
use App\Exceptions\InvalidEstimateTransition;
use App\Exceptions\InvoiceNotIssuable;
use App\Mail\EstimateMail;
use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Mail\ServiceReminderMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AttachmentService;
use App\Services\CheckInService;
use App\Services\CustomerService;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\JobTemplateService;
use App\Services\ReportService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssertsStockInvariant;
use Tests\Support\CreatesRealUploads;
use Tests\Support\CreatesSignatures;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;
use Throwable;

/**
 * Where the Phase 2 features meet on one job (specs 27–35). Each owning spec
 * pins its own rules in its own scope; this file asserts them only as
 * consequences inside a longer sequence — an estimate is checked for change
 * after a discount, an issue, a void and a reopen, not after one line edit.
 *
 * Sequences A and B drive services; C drives the three HTTP routes and the
 * Artisan command, because the wiring between them is what it tests. Every
 * figure is a literal from spec 36's tables — none is computed by the code
 * under test. After every step of A and B, four invariants are asserted (see
 * assertInvariants()); after every command run of C, one.
 *
 * Mail is asserted with Mail::fake(), which records immediately: nothing here
 * claims to prove after-commit behaviour (MailAfterCommitTest and
 * ReminderAfterCommitTest own that). Opening stock is built through
 * StockService::record(), never PartFactory::inStock(), whose quantity has no
 * movement behind it.
 */
class PhaseTwoSequencesTest extends TestCase
{
    use AssertsStockInvariant;
    use CreatesRealUploads;
    use CreatesSignatures;
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    private CarbonImmutable $today;

    private User $desk;

    private User $admin;

    private User $mechanic;

    /** @var array<string, Part> */
    private array $parts = [];

    private JobTemplate $template;

    /** @var array<int, array<string, mixed>> */
    private array $frozenEstimates = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $frozenEstimateLines = [];

    /** @var array<int, array<string, mixed>> */
    private array $frozenVoidInvoices = [];

    /** @var array<int, array<string, mixed>> */
    private array $frozenBuyers = [];

    /** @var array<string, mixed>|null */
    private ?array $frozenSheet = null;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake(config('attachments.disk'));

        config([
            'shop.labor_rate' => 3000,
            'shop.vat_rate' => 20,
            'shop.reminders.lead_days' => 14,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', 'Europe/Belgrade'));
        $this->today = CarbonImmutable::today();

        $this->desk = User::factory()->receptionist()->create();
        $this->admin = User::factory()->admin()->create();
        $this->mechanic = User::factory()->mechanic()->create();
    }

    /**
     * Sequence A — one job for a company customer, from intake to a corrected
     * invoice: check-in (32), template (31), estimates (33), discount (30),
     * buyer snapshot (27), payment method (28), void (19) and reports (35).
     *
     * Rules asserted as consequences: a mechanic's template lines are priced
     * from the catalogue and the labor rate and assigned to that mechanic; an
     * estimate equals the live totals when created and a revision leaves the
     * superseded one untouched; the warning follows the newest estimate; a
     * completed, unlocked job accepts a discount, which moves no stock and
     * changes no estimate; the invoice snapshots the buyer and the discount;
     * editing the customer changes no invoice and no export line; voiding keeps
     * totals, buyer, paid_at and payment_method and moves no stock; reopening
     * keeps the discount and a quantity edit moves the difference only;
     * re-issuing takes a fresh snapshot and is not refused by a pending
     * estimate; the report counts the re-issued invoice once.
     */
    public function test_sequence_a_the_quoted_job(): void
    {
        $this->buildCatalogue(['filter' => 5, 'oil' => 10, 'pads' => 3]);
        $this->buildTemplate();

        $jobs = $this->service();
        $items = app(JobItemService::class);
        $estimates = app(EstimateService::class);
        $invoices = app(InvoiceService::class);
        $checkIns = app(CheckInService::class);

        $customer = Customer::factory()->company()->create([
            'name' => 'Autoprevoz d.o.o.',
            'email' => 'fleet@autoprevoz.test',
            'address' => 'Bulevar oslobođenja 1, Novi Sad',
        ]);
        $issuedTaxId = $customer->tax_id;
        $issuedRegistration = $customer->registration_number;
        $this->assertNotNull($issuedTaxId);
        $this->assertNotNull($issuedRegistration);
        $vehicle = Vehicle::factory()->for($customer)->create();

        // A1 — walk-in; sheet recorded and signed on paper
        $job = $jobs->createWalkIn($vehicle, $this->desk, ['complaint' => 'Service due, brakes squeal']);
        $checkIns->record($job, $this->desk, ['fuel_level' => FuelLevel::Half, 'damage_notes' => 'Scratch on the rear bumper', 'valuables' => null]);
        $checkIns->sign($job, $this->desk, SignatureMethod::Paper, 'Petar Petrović', null);
        $this->freezeSheet($job);

        $this->assertSame(JobStatus::Received, $job->fresh()->status);
        $this->assertNotNull($this->sheetRow($job)['signed_at']);
        $this->assertInvariants('A1', ['filter' => 5, 'oil' => 10, 'pads' => 3], 3);

        // A2 — mechanic starts the job and applies the template
        $jobs->start($job->fresh());
        $items->applyTemplate($job->fresh(), $this->template, $this->mechanic, null);
        [$laborLine, $filterLine, $oilLine] = $this->linesOf($job);

        $this->assertSame(['4500.00', '900.00', '4800.00'], $this->lineTotals($job), 'A2');
        $this->assertSame([$this->mechanic->getKey()], $this->lineAssignees($job), 'A2: every template line belongs to the mechanic.');
        $this->assertInvariants('A2', ['filter' => 4, 'oil' => 6, 'pads' => 3], 5);

        // A3 — front office creates E1
        $e1 = $estimates->create($job->fresh(), $this->desk);
        $this->freezeEstimate($e1);

        $this->assertEstimate('A3', $e1, EstimateStatus::Pending, '10200.00', '0.00', '2040.00', '12240.00', 3);
        $this->assertWarning('A3', $job, 'not_approved', null);
        $this->assertInvariants('A3', ['filter' => 4, 'oil' => 6, 'pads' => 3], 5);

        // A4 — E1 approved by phone
        $estimates->decide($e1, EstimateStatus::Approved, DecisionMethod::Phone, null, $this->desk);
        $this->refreshEstimate($e1, ['status', 'decision_method', 'decided_at', 'decided_by', 'decision_note']);

        $this->assertWarning('A4', $job, null, null);
        $this->assertInvariants('A4', ['filter' => 4, 'oil' => 6, 'pads' => 3], 5);

        // A5 — front office adds brake pads × 1, unassigned
        $padsLine = $items->addPart($job->fresh(), $this->parts['pads'], $this->desk, ['quantity' => 1]);

        $this->assertNull($padsLine->fresh()->user_id, 'A5: the pads line is unassigned.');
        $this->assertSame('16440.00', $this->liveTotals($job)['total'], 'A5');
        $this->assertWarning('A5', $job, 'over', '4200.00');
        $this->assertInvariants('A5', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A6 — front office revises
        $e2 = $estimates->create($job->fresh(), $this->desk);
        $this->refreshEstimate($e1, ['status', 'superseded_at']);
        $this->freezeEstimate($e2);

        $this->assertEstimate('A6', $e2, EstimateStatus::Pending, '13700.00', '0.00', '2740.00', '16440.00', 4);
        $this->assertEstimate('A6', $e1, EstimateStatus::Superseded, '10200.00', '0.00', '2040.00', '12240.00', 3);
        $this->assertWarning('A6', $job, 'not_approved', null);
        $this->assertInvariants('A6', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A7 — E2 approved in person
        $estimates->decide($e2, EstimateStatus::Approved, DecisionMethod::InPerson, null, $this->desk);
        $this->refreshEstimate($e2, ['status', 'decision_method', 'decided_at', 'decided_by', 'decision_note']);

        $this->assertWarning('A7', $job, null, null);
        $this->assertInvariants('A7', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A8 — job completed
        $jobs->complete($job->fresh());

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertMailCounts('A8', completed: 1, issued: 0, estimate: 0);
        $this->assertMailRecipients('A8', JobCompletedMail::class, ['fleet@autoprevoz.test']);
        $this->assertInvariants('A8', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A9 — front office sets 10% on the labor line of the completed job
        $items->setDiscount($laborLine->fresh(), '10.00');

        $this->assertSame('4050.00', $laborLine->fresh()->lineTotal(), 'A9');
        $this->assertSame(
            ['subtotal' => '13250.00', 'discount' => '450.00', 'tax' => '2650.00', 'total' => '15900.00'],
            $this->only($this->liveTotals($job), ['subtotal', 'discount', 'tax', 'total']),
            'A9',
        );
        $this->assertWarning('A9', $job, null, null);
        $this->assertInvariants('A9', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A10 — INV1 issued
        $inv1 = $invoices->issue($job->fresh(), $this->desk);
        $this->freezeBuyer($inv1);

        $this->assertInvoice('A10', $inv1, InvoiceStatus::Unpaid, '13250.00', '450.00', '2650.00', '15900.00');
        $this->assertSame(
            ['buyer_name' => 'Autoprevoz d.o.o.', 'buyer_address' => 'Bulevar oslobođenja 1, Novi Sad', 'buyer_tax_id' => $issuedTaxId, 'buyer_registration_number' => $issuedRegistration],
            $this->buyerOf($inv1),
            'A10: the invoice names the buyer as a company.',
        );
        $this->assertNotNull($job->fresh()->locked_at, 'A10: issuing locks the job.');
        $this->assertMailCounts('A10', completed: 1, issued: 1, estimate: 0);
        $this->assertMailRecipients('A10', InvoiceIssuedMail::class, ['fleet@autoprevoz.test']);
        $this->assertSame('16440.00', $e2->fresh()->total);
        $this->assertSame('540.00', bcsub($e2->fresh()->total, $inv1->fresh()->total, 2), 'A10: the approved estimate and the invoice differ by the discount and its VAT.');
        $this->assertInvariants('A10', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A11 — report for the day
        $this->assertReport(
            'A11',
            ['invoices' => 1, 'voided' => 0, 'before_discount' => '13700.00', 'discount' => '450.00', 'subtotal' => '13250.00', 'vat' => '2650.00', 'total' => '15900.00'],
            ['unpaid' => [1, '15900.00']],
            [[$this->mechanic->getKey(), '1.50', '4050.00']],
            '9200.00',
            [['OIL-1L', 4, '4800.00'], ['BP-200', 1, '3500.00'], ['OF-100', 1, '900.00']],
        );

        // A12 — INV1 marked paid by bank transfer
        $invoices->markPaid($inv1, PaymentMethod::BankTransfer);

        $this->assertSame(['bank_transfer' => [1, '15900.00']], $this->paymentRows(), 'A12');
        $this->assertInvariants('A12', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A13 — K renamed, PIB and MB cleared
        app(CustomerService::class)->update($customer, ['name' => 'Autoprevoz Novi', 'tax_id' => null, 'registration_number' => null]);

        $this->assertSame('Autoprevoz d.o.o.', $this->buyerOf($inv1)['buyer_name'], 'A13');
        $this->assertSame($issuedTaxId, $this->buyerOf($inv1)['buyer_tax_id'], 'A13');
        $exported = $this->exportRows();
        $this->assertCount(1, $exported, 'A13');
        $this->assertSame([$inv1->number, 'Autoprevoz d.o.o.', $issuedTaxId], [$exported[0][0], $exported[0][4], $exported[0][6]], 'A13: the export carries the buyer as issued.');

        // INV1's queued email, round-tripped through serialize() as the queue
        // worker would, so SerializesModels re-reads its models AFTER the
        // rename: it still addresses the company as issued (spec 27 × 21).
        $queuedInvoiceMail = unserialize(serialize(Mail::queued(InvoiceIssuedMail::class)->first()))->render();
        $this->assertStringContainsString('Autoprevoz d.o.o.', $queuedInvoiceMail, 'A13');
        $this->assertStringContainsString($issuedTaxId, $queuedInvoiceMail, 'A13');
        $this->assertStringNotContainsString('Autoprevoz Novi', $queuedInvoiceMail, 'A13: the email read the live customer.');
        $this->assertInvariants('A13', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A14 — admin voids INV1
        $beforeVoid = $this->invoiceRow($inv1);
        $invoices->void($inv1, $this->admin);
        $this->freezeVoidInvoice($inv1);
        $afterVoid = $this->invoiceRow($inv1);

        $kept = ['subtotal', 'discount_total', 'tax_rate', 'tax_amount', 'total', 'buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_registration_number', 'paid_at', 'payment_method'];
        $this->assertSame('void', $afterVoid['status'], 'A14');
        $this->assertSame(array_intersect_key($beforeVoid, array_flip($kept)), array_intersect_key($afterVoid, array_flip($kept)), 'A14: voiding kept totals, buyer, paid_at and payment_method.');
        $this->assertSame('bank_transfer', $afterVoid['payment_method'], 'A14');
        $this->assertNull($job->fresh()->locked_at, 'A14: voiding unlocks the job.');
        $this->assertSame(JobStatus::Completed, $job->fresh()->status, 'A14');
        $this->assertReport(
            'A14',
            ['invoices' => 0, 'voided' => 1, 'before_discount' => '0.00', 'discount' => '0.00', 'subtotal' => '0.00', 'vat' => '0.00', 'total' => '0.00'],
            [],
            [],
            '0.00',
            [],
        );
        $exported = $this->exportRows();
        $this->assertCount(1, $exported, 'A14: the export still lists the void invoice.');
        $this->assertSame([$inv1->number, 'Void'], [$exported[0][0], $exported[0][2]], 'A14');
        $this->assertInvariants('A14', ['filter' => 4, 'oil' => 6, 'pads' => 2], 6);

        // A15 — reopen; the oil quantity 4 → 5, M named as the line's mechanic
        $jobs->reopen($job->fresh());
        $items->update($oilLine->fresh(), $this->desk, ['quantity' => '5', 'user_id' => $this->mechanic->getKey()]);

        $this->assertSame(JobStatus::InProgress, $job->fresh()->status, 'A15');
        $newest = StockMovement::query()->latest('id')->firstOrFail();
        $this->assertSame([StockMovementReason::JobUse, -1, $this->parts['oil']->getKey()], [$newest->reason, $newest->quantity_change, $newest->part_id], 'A15: the edit moved the difference only.');
        $this->assertSame('10.00', $laborLine->fresh()->discount_percent, 'A15: the discount survived the void.');
        $this->assertSame('4050.00', $laborLine->fresh()->lineTotal(), 'A15');
        $this->assertSame(
            ['subtotal' => '14450.00', 'tax' => '2890.00', 'total' => '17340.00'],
            $this->only($this->liveTotals($job), ['subtotal', 'tax', 'total']),
            'A15',
        );
        $this->assertWarning('A15', $job, 'over', '900.00');
        $this->assertInvariants('A15', ['filter' => 4, 'oil' => 5, 'pads' => 2], 7);

        // A16 — front office revises
        $e3 = $estimates->create($job->fresh(), $this->desk);
        $this->refreshEstimate($e2, ['status', 'superseded_at']);
        $this->freezeEstimate($e3);

        $this->assertEstimate('A16', $e3, EstimateStatus::Pending, '14450.00', '450.00', '2890.00', '17340.00', 4);
        $this->assertSame(['10.00', '4050.00'], [$e3->lines()->first()->discount_percent, $e3->lines()->first()->line_total], 'A16: the labor line carried its discount onto E3.');
        $this->assertEstimate('A16', $e2, EstimateStatus::Superseded, '13700.00', '0.00', '2740.00', '16440.00', 4);
        $this->assertWarning('A16', $job, 'not_approved', null);
        $this->assertInvariants('A16', ['filter' => 4, 'oil' => 5, 'pads' => 2], 7);

        // A17 — completed again; INV2 issued with E3 still pending
        $jobs->complete($job->fresh());
        $inv2 = $invoices->issue($job->fresh(), $this->desk);
        $this->freezeBuyer($inv2);

        $this->assertMailCounts('A17', completed: 2, issued: 2, estimate: 0);
        $this->assertMailRecipients('A17', InvoiceIssuedMail::class, ['fleet@autoprevoz.test', 'fleet@autoprevoz.test']);
        $this->assertNotSame($inv1->number, $inv2->number, 'A17: a re-issue takes a new number.');
        $this->assertInvoice('A17', $inv2, InvoiceStatus::Unpaid, '14450.00', '450.00', '2890.00', '17340.00');
        $this->assertSame(
            ['buyer_name' => 'Autoprevoz Novi', 'buyer_address' => 'Bulevar oslobođenja 1, Novi Sad', 'buyer_tax_id' => null, 'buyer_registration_number' => null],
            $this->buyerOf($inv2),
            'A17: the re-issue snapshots the customer as edited.',
        );
        $this->assertNotNull($job->fresh()->locked_at, 'A17');
        $this->assertInvariants('A17', ['filter' => 4, 'oil' => 5, 'pads' => 2], 7);

        // A18 — report for the day
        $this->assertReport(
            'A18',
            ['invoices' => 1, 'voided' => 1, 'before_discount' => '14900.00', 'discount' => '450.00', 'subtotal' => '14450.00', 'vat' => '2890.00', 'total' => '17340.00'],
            ['unpaid' => [1, '17340.00']],
            [[$this->mechanic->getKey(), '1.50', '4050.00']],
            '10400.00',
            [['OIL-1L', 5, '6000.00'], ['BP-200', 1, '3500.00'], ['OF-100', 1, '900.00']],
        );
        $exported = $this->exportRows();
        $this->assertSame(
            [[$inv1->number, 'Void'], [$inv2->number, 'Unpaid']],
            array_map(fn (array $row): array => [$row[0], $row[2]], $exported),
            'A18: a header and two data lines, in id order.',
        );

        // Final state
        $this->assertSame(['job_use' => 4, 'restock' => 3], $this->movementsByReason(), 'Final: no job_return.');
        $this->assertSame(
            ['superseded', 'superseded', 'pending'],
            Estimate::query()->where('job_id', $job->getKey())->orderBy('id')->pluck('status')->map(fn (EstimateStatus $status): string => $status->value)->all(),
        );
        $this->assertMailCounts('Final', completed: 2, issued: 2, estimate: 0);
        $this->assertSame($this->frozenSheet, $this->sheetRow($job), 'Final: the signed sheet is as it was signed.');
    }

    /**
     * Sequence B — a job opened from an appointment, quoted, declined and
     * cancelled: arrival (09), a drawn check-in with a photo (32), template
     * (31), the estimate email and decision (33) and cancellation (16).
     *
     * Step B6 holds seven refusals, as spec 36's table lists them, and only
     * ONE of them is caused by the cancellation: creating a new estimate
     * (EstimateNotCreatable, because the job is no longer working). The other
     * six are end-state guards. Deciding and emailing E1 are refused because
     * E1 is declined; recording, signing and deleting the sheet's photo because
     * the sheet was signed in B2; issuing because the job was never completed.
     * They confirm the finished job stays untouched, and they would pass with
     * the cancel removed — they cannot detect a broken cancel. What does is B5:
     * the two job_return movements and the balances back at 5 and 10.
     * (Spec 36, Clarification 1.)
     */
    public function test_sequence_b_the_declined_quote(): void
    {
        $this->buildCatalogue(['filter' => 5, 'oil' => 10]);
        $this->buildTemplate();

        $jobs = $this->service();
        $estimates = app(EstimateService::class);
        $checkIns = app(CheckInService::class);

        $customer = Customer::factory()->create(['email' => 'driver@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $appointment = Appointment::factory()->booked()->create(['customer_id' => $customer->getKey(), 'vehicle_id' => $vehicle->getKey()]);

        // B1 — the booked appointment arrives
        $job = $jobs->createFromAppointment($appointment, $this->desk, ['complaint' => 'Oil change and a quote for the rest']);

        $this->assertSame(JobStatus::Received, $job->fresh()->status, 'B1');
        $this->assertSame($appointment->getKey(), $job->fresh()->appointment_id, 'B1');
        $this->assertTrue($appointment->fresh()->isArrived(), 'B1');
        $this->assertInvariants('B1', ['filter' => 5, 'oil' => 10], 2);

        // B2 — sheet recorded, one photo added, signed with a drawn signature
        $checkIns->record($job, $this->desk, ['fuel_level' => FuelLevel::Quarter, 'damage_notes' => 'Dent in the driver door', 'valuables' => 'Child seat']);
        $photo = $checkIns->addPhoto($job, $this->realImageUpload('dent.jpg', 400, 300), $this->desk);
        $checkIns->sign($job, $this->desk, SignatureMethod::Drawn, 'Jovana Ilić', $this->signatureDataUrl());
        $this->freezeSheet($job);
        $sheet = $this->sheetRow($job);

        $this->assertSame('drawn', $sheet['signature_method'], 'B2');
        $this->assertNotNull($sheet['signed_at'], 'B2');
        Storage::disk(config('attachments.disk'))->assertExists($sheet['signature_path']);
        $this->assertSame(1, JobAttachment::query()->where('job_id', $job->getKey())->where('category', AttachmentCategory::CheckIn)->count(), 'B2');
        $this->assertInvariants('B2', ['filter' => 5, 'oil' => 10], 2);

        // B3 — mechanic starts the job and applies the template
        $jobs->start($job->fresh());
        app(JobItemService::class)->applyTemplate($job->fresh(), $this->template, $this->mechanic, null);

        $this->assertCount(3, $this->linesOf($job), 'B3');
        $this->assertInvariants('B3', ['filter' => 4, 'oil' => 6], 4);

        // B4 — E1 created, emailed, then declined by phone
        $e1 = $estimates->create($job->fresh(), $this->desk);
        $this->freezeEstimate($e1);
        $this->assertTrue($estimates->email($e1), 'B4');
        $this->refreshEstimate($e1, ['emailed_at']);
        $estimates->decide($e1, EstimateStatus::Declined, DecisionMethod::Phone, 'Will do it elsewhere.', $this->desk);
        $this->refreshEstimate($e1, ['status', 'decision_method', 'decided_at', 'decided_by', 'decision_note']);

        $this->assertMailCounts('B4', completed: 0, issued: 0, estimate: 1);
        $this->assertMailRecipients('B4', EstimateMail::class, ['driver@example.test']);
        $this->assertNotNull($e1->fresh()->emailed_at, 'B4');
        $this->assertSame(EstimateStatus::Declined, $e1->fresh()->status, 'B4');
        $this->assertWarning('B4', $job, 'declined', null);
        $this->assertInvariants('B4', ['filter' => 4, 'oil' => 6], 4);

        // B5 — front office cancels the job
        $jobs->cancel($job->fresh(), $this->desk);

        $this->assertSame(JobStatus::Cancelled, $job->fresh()->status, 'B5');
        $this->assertSame(['job_return' => 2, 'job_use' => 2, 'restock' => 2], $this->movementsByReason(), 'B5');
        $this->assertCount(3, $this->linesOf($job), 'B5: cancelling leaves the lines in place.');
        $this->assertInvariants('B5', ['filter' => 5, 'oil' => 10], 6);

        $photoPaths = $photo->fresh()->storedPaths();
        $this->assertNotEmpty($photoPaths);
        $afterCancel = [
            'estimate' => $this->estimateRow($e1->getKey()),
            'sheet' => $this->sheetRow($job),
            'photo' => (array) DB::table('job_attachments')->where('id', $photo->getKey())->first(),
            'stock' => DB::table('parts')->orderBy('id')->pluck('quantity_in_stock', 'id')->all(),
            'movements' => StockMovement::query()->count(),
            'estimates' => Estimate::query()->count(),
            'invoices' => Invoice::query()->count(),
        ];

        // B6 — every later write refused, one at a time
        $this->assertRefused('B6 decide E1', InvalidEstimateTransition::class, fn () => $estimates->decide($e1->fresh(), EstimateStatus::Approved, DecisionMethod::Phone, null, $this->desk));
        $this->assertRefused('B6 email E1', InvalidEstimateTransition::class, fn () => $estimates->email($e1->fresh()));
        $this->assertRefused('B6 create an estimate', EstimateNotCreatable::class, fn () => $estimates->create($job->fresh(), $this->desk));
        $this->assertRefused('B6 record the sheet', CheckInNotOpen::class, fn () => $checkIns->record($job->fresh(), $this->desk, ['fuel_level' => FuelLevel::Full]));
        $this->assertRefused('B6 sign the sheet', CheckInNotOpen::class, fn () => $checkIns->sign($job->fresh(), $this->desk, SignatureMethod::Paper, 'Someone Else', null));
        $this->assertRefused('B6 delete the photo', CheckInNotOpen::class, fn () => app(AttachmentService::class)->delete($photo->fresh()));
        $this->assertRefused('B6 issue an invoice', InvoiceNotIssuable::class, fn () => app(InvoiceService::class)->issue($job->fresh(), $this->desk));

        $this->assertSame($afterCancel, [
            'estimate' => $this->estimateRow($e1->getKey()),
            'sheet' => $this->sheetRow($job),
            'photo' => (array) DB::table('job_attachments')->where('id', $photo->getKey())->first(),
            'stock' => DB::table('parts')->orderBy('id')->pluck('quantity_in_stock', 'id')->all(),
            'movements' => StockMovement::query()->count(),
            'estimates' => Estimate::query()->count(),
            'invoices' => Invoice::query()->count(),
        ], 'B6: a refusal changed a stored row.');

        foreach ($photoPaths as $path) {
            Storage::disk(config('attachments.disk'))->assertExists($path);
        }

        $this->assertInvariants('B6', ['filter' => 5, 'oil' => 10], 6);

        // B7 — report for the day
        $this->assertReport(
            'B7',
            ['invoices' => 0, 'voided' => 0, 'before_discount' => '0.00', 'discount' => '0.00', 'subtotal' => '0.00', 'vat' => '0.00', 'total' => '0.00'],
            [],
            [],
            '0.00',
            [],
        );
        $this->assertWarning('B7', $job, 'declined', null);

        Mail::assertQueuedCount(1);
        $this->assertMailCounts('Final', completed: 0, issued: 0, estimate: 1);
    }

    /**
     * Sequence C — one vehicle from a locked job's Next service page to the
     * customer's inbox and the dashboard, through two date changes and a sale
     * (spec 34). Through HTTP and the Artisan command, because the wiring
     * between the page, the command and the panel is what this tests.
     *
     * Rules asserted as consequences: a date saved from a locked job changes
     * the vehicle and nothing on the job; the command queues one reminder per
     * due date and a re-run queues nothing; a moved date is reminded again and
     * a date moved back is not; after a sale, a date reminded to the previous
     * owner is not re-sent and the panel shows the new owner's phone, while a
     * new date is sent to the new owner.
     *
     * The panel's emailed state reads vehicle_reminders, so both the queued
     * mail and the row are asserted after every run.
     */
    public function test_sequence_c_the_reminder_chain(): void
    {
        $ownerA = Customer::factory()->create(['email' => 'owner.a@example.test', 'phone' => '063 111 222', 'receives_reminders' => true]);
        $ownerB = Customer::factory()->create(['email' => 'owner.b@example.test', 'phone' => '064 333 444', 'receives_reminders' => true]);
        $vehicle = Vehicle::factory()->for($ownerA)->create(['next_service_on' => null, 'next_service_mileage' => null, 'registration_expires_on' => null]);

        $job = Job::factory()->completed()->create(['vehicle_id' => $vehicle->getKey(), 'customer_id' => $ownerA->getKey()]);
        JobItem::factory()->forJob($job)->performedBy($this->mechanic)->create();
        app(InvoiceService::class)->issue($job, $this->desk);
        $lockedJob = (array) DB::table('jobs')->where('id', $job->getKey())->first();
        $this->assertNotNull($lockedJob['locked_at'], 'The job must really be locked.');

        $t10 = $this->today->addDays(10)->toDateString();
        $t12 = $this->today->addDays(12)->toDateString();
        $t13 = $this->today->addDays(13)->toDateString();

        // C1 — the locked job's Next service page, a minute after the job was
        // invoiced, so a write that only touched the job row would show up.
        $this->travel(1)->minutes();
        $this->actingAs($this->desk)
            ->put(route('jobs.next-service.update', $job), ['next_service_on' => $t10, 'next_service_mileage' => '', 'registration_expires_on' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame($t10, $vehicle->fresh()->next_service_on->toDateString(), 'C1');
        $this->assertSame($lockedJob, (array) DB::table('jobs')->where('id', $job->getKey())->first(), 'C1: the job row is unchanged and still locked.');

        // C2 — first run
        $this->runReminders('C2', inserts: 1);
        $this->assertReminders('C2', [[$vehicle->getKey(), 'service', $t10, $ownerA->getKey()]], [$ownerA->email]);
        $this->assertDueSoonRow('C2', $vehicle, 'emailed', $ownerA->phone, $ownerB->phone);

        // C3 — second run
        $this->runReminders('C3', inserts: 0);
        $this->assertReminders('C3', [[$vehicle->getKey(), 'service', $t10, $ownerA->getKey()]], [$ownerA->email]);
        $this->assertDueSoonRow('C3', $vehicle, 'emailed', $ownerA->phone, $ownerB->phone);

        // C4 — moved to T+12 on the vehicle form
        $this->updateVehicleForm($vehicle, $t12);
        $this->runReminders('C4', inserts: 1);
        $this->assertReminders('C4', [
            [$vehicle->getKey(), 'service', $t10, $ownerA->getKey()],
            [$vehicle->getKey(), 'service', $t12, $ownerA->getKey()],
        ], [$ownerA->email, $ownerA->email]);
        $this->assertDueSoonRow('C4', $vehicle, 'emailed', $ownerA->phone, $ownerB->phone);

        // C5 — moved back to T+10, already reminded
        $this->updateVehicleForm($vehicle, $t10);
        $this->runReminders('C5', inserts: 0);
        $this->assertReminders('C5', [
            [$vehicle->getKey(), 'service', $t10, $ownerA->getKey()],
            [$vehicle->getKey(), 'service', $t12, $ownerA->getKey()],
        ], [$ownerA->email, $ownerA->email]);
        $this->assertDueSoonRow('C5', $vehicle, 'emailed', $ownerA->phone, $ownerB->phone);

        // C6 — admin reassigns V to B
        $this->actingAs($this->admin)
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => $ownerB->getKey()])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vehicles.show', $vehicle));
        $this->assertSame($ownerB->getKey(), $vehicle->fresh()->customer_id, 'C6');

        $this->runReminders('C6', inserts: 0);
        $this->assertReminders('C6', [
            [$vehicle->getKey(), 'service', $t10, $ownerA->getKey()],
            [$vehicle->getKey(), 'service', $t12, $ownerA->getKey()],
        ], [$ownerA->email, $ownerA->email]);
        $this->assertDueSoonRow('C6', $vehicle, 'call', $ownerB->phone, $ownerA->phone);

        // C7 — moved to T+13, a date the new owner was never reminded about
        $this->updateVehicleForm($vehicle, $t13);
        $this->runReminders('C7', inserts: 1);
        $this->assertReminders('C7', [
            [$vehicle->getKey(), 'service', $t10, $ownerA->getKey()],
            [$vehicle->getKey(), 'service', $t12, $ownerA->getKey()],
            [$vehicle->getKey(), 'service', $t13, $ownerB->getKey()],
        ], [$ownerA->email, $ownerA->email, $ownerB->email]);
        $this->assertDueSoonRow('C7', $vehicle, 'emailed', $ownerB->phone, $ownerA->phone);
    }

    // ----- the per-step invariants (A and B) -----

    /**
     * 1. Stock equals its ledger, with every balance and the movement count
     *    pinned as literals — the ledger check alone cannot see a step that
     *    skips both the quantity write and the movement.
     * 2. At most one non-void invoice and one open estimate per job.
     * 3. Frozen records unchanged: estimates and their lines, void invoices,
     *    every invoice's buyer_* columns, the signed sheet.
     * 4. The report agrees with the invoices table for the day: spec 35's
     *    invariants 1–4, and revenue equal to the non-void totals read here.
     *
     * @param  array<string, int>  $balances
     */
    private function assertInvariants(string $step, array $balances, int $movements): void
    {
        foreach ($balances as $name => $expected) {
            $this->assertSame($expected, (int) DB::table('parts')->where('id', $this->parts[$name]->getKey())->value('quantity_in_stock'), "{$step}: {$name} stock");
        }

        $this->assertSame($movements, StockMovement::query()->count(), "{$step}: movement count");
        $this->assertStockMatchesLedger();

        $this->assertSame([], DB::table('invoices')->where('status', '!=', InvoiceStatus::Void->value)->groupBy('job_id')->havingRaw('COUNT(*) > 1')->pluck('job_id')->all(), "{$step}: a job has two non-void invoices.");
        $this->assertSame([], DB::table('estimates')->whereIn('status', [EstimateStatus::Pending->value, EstimateStatus::Approved->value])->groupBy('job_id')->havingRaw('COUNT(*) > 1')->pluck('job_id')->all(), "{$step}: a job has two open estimates.");

        foreach ($this->frozenEstimates as $id => $row) {
            $this->assertSame($row, $this->estimateRow($id), "{$step}: estimate {$id} changed.");
            $this->assertSame($this->frozenEstimateLines[$id], $this->estimateLineRows($id), "{$step}: the lines of estimate {$id} changed.");
        }

        foreach ($this->frozenVoidInvoices as $id => $row) {
            $this->assertSame($row, (array) DB::table('invoices')->where('id', $id)->first(), "{$step}: void invoice {$id} changed.");
        }

        foreach ($this->frozenBuyers as $id => $buyer) {
            $this->assertSame($buyer, $this->buyerOf(Invoice::query()->findOrFail($id)), "{$step}: the buyer on invoice {$id} changed.");
        }

        if ($this->frozenSheet !== null) {
            $this->assertSame($this->frozenSheet, (array) DB::table('job_check_ins')->where('id', $this->frozenSheet['id'])->first(), "{$step}: the signed sheet changed.");
        }

        $reports = app(ReportService::class);
        $revenue = $reports->revenue($this->today, $this->today);
        $vat = $reports->vatByRate($this->today, $this->today);
        $payments = $reports->payments($this->today, $this->today);
        $labor = $reports->laborByMechanic($this->today, $this->today);
        $parts = $reports->partsSold($this->today, $this->today, false);

        $this->assertSame($revenue['total'], bcadd($revenue['subtotal'], $revenue['vat'], 2), "{$step}: report invariant 1");
        $this->assertSame($revenue['subtotal'], $this->sum(array_column($vat, 'base')), "{$step}: report invariant 2 (base)");
        $this->assertSame($revenue['vat'], $this->sum(array_column($vat, 'vat')), "{$step}: report invariant 2 (vat)");
        $this->assertSame($revenue['total'], $this->sum(array_column($payments, 'amount')), "{$step}: report invariant 3 (amount)");
        $this->assertSame($revenue['invoices'], array_sum(array_column($payments, 'invoices')), "{$step}: report invariant 3 (count)");
        $this->assertSame($revenue['subtotal'], bcadd($this->sum(array_column($labor, 'amount')), $parts['revenue'], 2), "{$step}: report invariant 4");

        $table = DB::table('invoices')
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->whereDate('issued_at', $this->today->toDateString())
            ->pluck('total')
            ->all();

        $this->assertSame($this->sum($table), $revenue['total'], "{$step}: the report's revenue is not the sum of the non-void invoices.");

        // Every step runs a minute after the last, still on the same day, so a
        // write that re-stamps a frozen row (paid_at, updated_at) shows up as a
        // change instead of hiding behind an identical frozen instant.
        $this->travel(1)->minutes();
    }

    private function freezeEstimate(Estimate $estimate): void
    {
        $this->frozenEstimates[$estimate->getKey()] = $this->estimateRow($estimate->getKey());
        $this->frozenEstimateLines[$estimate->getKey()] = $this->estimateLineRows($estimate->getKey());
    }

    /**
     * A step that legitimately writes an estimate's status or decision columns
     * refreshes the copy for those columns (and updated_at) only.
     *
     * @param  list<string>  $columns
     */
    private function refreshEstimate(Estimate $estimate, array $columns): void
    {
        $current = $this->estimateRow($estimate->getKey());

        foreach ([...$columns, 'updated_at'] as $column) {
            $this->frozenEstimates[$estimate->getKey()][$column] = $current[$column];
        }
    }

    private function freezeVoidInvoice(Invoice $invoice): void
    {
        $this->frozenVoidInvoices[$invoice->getKey()] = $this->invoiceRow($invoice);
    }

    private function freezeBuyer(Invoice $invoice): void
    {
        $this->frozenBuyers[$invoice->getKey()] = $this->buyerOf($invoice);
    }

    private function freezeSheet(Job $job): void
    {
        $this->frozenSheet = $this->sheetRow($job);
    }

    // ----- builders -----

    /**
     * @param  array<string, int>  $opening
     */
    private function buildCatalogue(array $opening): void
    {
        $catalogue = [
            'filter' => ['sku' => 'OF-100', 'name' => 'Oil filter', 'unit_price' => '900.00'],
            'oil' => ['sku' => 'OIL-1L', 'name' => 'Engine oil 1 L', 'unit_price' => '1200.00'],
            'pads' => ['sku' => 'BP-200', 'name' => 'Brake pads, front', 'unit_price' => '3500.00'],
        ];

        foreach ($opening as $name => $quantity) {
            $this->parts[$name] = Part::factory()->create($catalogue[$name]);
            app(StockService::class)->record($this->parts[$name], $quantity, StockMovementReason::Restock, $this->desk);
        }
    }

    private function buildTemplate(): void
    {
        $templates = app(JobTemplateService::class);

        $this->template = $templates->create(['name' => 'Small service', 'description' => null]);
        $templates->addItem($this->template, JobItemType::Labor, ['description' => 'Oil service', 'quantity' => '1.50']);
        $templates->addItem($this->template, JobItemType::Part, ['part_id' => $this->parts['filter']->getKey(), 'quantity' => '1']);
        $templates->addItem($this->template, JobItemType::Part, ['part_id' => $this->parts['oil']->getKey(), 'quantity' => '4']);
    }

    // ----- readers -----

    /**
     * @return list<JobItem>
     */
    private function linesOf(Job $job): array
    {
        return JobItem::query()->where('job_id', $job->getKey())->orderBy('id')->get()->all();
    }

    /**
     * @return list<string>
     */
    private function lineTotals(Job $job): array
    {
        return array_map(fn (JobItem $line): string => $line->lineTotal(), $this->linesOf($job));
    }

    /**
     * @return list<int|null>
     */
    private function lineAssignees(Job $job): array
    {
        return array_values(array_unique(array_map(fn (JobItem $line): ?int => $line->user_id, $this->linesOf($job))));
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    private function liveTotals(Job $job): array
    {
        return app(InvoiceService::class)->totalsFor(Job::query()->with('items')->findOrFail($job->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateRow(int $id): array
    {
        return (array) DB::table('estimates')->where('id', $id)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function estimateLineRows(int $estimateId): array
    {
        return DB::table('estimate_lines')->where('estimate_id', $estimateId)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $invoice): array
    {
        return (array) DB::table('invoices')->where('id', $invoice->getKey())->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerOf(Invoice $invoice): array
    {
        return (array) DB::table('invoices')
            ->where('id', $invoice->getKey())
            ->select(['buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_registration_number'])
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetRow(Job $job): array
    {
        return (array) DB::table('job_check_ins')->where('job_id', $job->getKey())->first();
    }

    /**
     * @return array<string, int>
     */
    private function movementsByReason(): array
    {
        return DB::table('stock_movements')
            ->selectRaw('reason, COUNT(*) AS aggregate')
            ->groupBy('reason')
            ->orderBy('reason')
            ->pluck('aggregate', 'reason')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * The export's data lines for the day, parsed, without the header row.
     *
     * @return list<list<string>>
     */
    private function exportRows(): array
    {
        $lines = iterator_to_array(app(ReportService::class)->exportLines($this->today, $this->today), false);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $lines[0], 'The export opens with its BOM and header row.');

        return array_map(
            fn (string $line): array => str_getcsv(rtrim($line, "\r\n"), ';', '"', '\\'),
            array_slice($lines, 1),
        );
    }

    /**
     * @return array<string, array{int, string}>
     */
    private function paymentRows(): array
    {
        $rows = [];

        foreach (app(ReportService::class)->payments($this->today, $this->today) as $row) {
            $rows[$row['key']] = [$row['invoices'], $row['amount']];
        }

        return $rows;
    }

    /**
     * $source's values for $keys, in the order $keys names them.
     *
     * @param  array<string, mixed>  $source
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $source, array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key): mixed => $source[$key], $keys));
    }

    /**
     * @param  list<string>  $values
     */
    private function sum(array $values): string
    {
        return array_reduce($values, fn (string $carry, mixed $value): string => bcadd($carry, (string) $value, 2), '0.00');
    }

    // ----- step assertions -----

    private function assertEstimate(string $step, Estimate $estimate, EstimateStatus $status, string $subtotal, string $discount, string $tax, string $total, int $lines): void
    {
        $fresh = $estimate->fresh();

        $this->assertSame(
            [$status, $subtotal, $discount, $tax, $total, $lines],
            [$fresh->status, $fresh->subtotal, $fresh->discount_total, $fresh->tax_amount, $fresh->total, $fresh->lines()->count()],
            "{$step}: estimate {$fresh->number}",
        );
    }

    private function assertInvoice(string $step, Invoice $invoice, InvoiceStatus $status, string $subtotal, string $discount, string $tax, string $total): void
    {
        $fresh = $invoice->fresh();

        $this->assertSame(
            [$status, $subtotal, $discount, '20.00', $tax, $total],
            [$fresh->status, $fresh->subtotal, $fresh->discount_total, $fresh->tax_rate, $fresh->tax_amount, $fresh->total],
            "{$step}: invoice {$fresh->number}",
        );
    }

    /**
     * @param  'over'|'not_approved'|'declined'|null  $warning
     */
    private function assertWarning(string $step, Job $job, ?string $warning, ?string $overBy): void
    {
        $summary = app(EstimateService::class)->summaryFor(Job::query()->with('items')->findOrFail($job->getKey()));

        $this->assertNotNull($summary, "{$step}: the job has an estimate.");
        $this->assertSame([$warning, $overBy], [$summary['warning'], $summary['over_by']], "{$step}: estimate warning");
    }

    private function assertMailCounts(string $step, int $completed, int $issued, int $estimate): void
    {
        $this->assertSame(
            ['completed' => $completed, 'issued' => $issued, 'estimate' => $estimate],
            [
                'completed' => Mail::queued(JobCompletedMail::class)->count(),
                'issued' => Mail::queued(InvoiceIssuedMail::class)->count(),
                'estimate' => Mail::queued(EstimateMail::class)->count(),
            ],
            "{$step}: customer mail queued so far",
        );
    }

    /**
     * @param  class-string<Mailable>  $mailable
     * @param  list<string>  $addresses
     */
    private function assertMailRecipients(string $step, string $mailable, array $addresses): void
    {
        $this->assertSame(
            $addresses,
            Mail::queued($mailable)->map(fn (Mailable $mail): string => $mail->to[0]['address'])->values()->all(),
            "{$step}: {$mailable} recipients",
        );
    }

    /**
     * @param  array<string, int|string>  $revenue
     * @param  array<string, array{int, string}>  $payments
     * @param  list<array{int, string, string}>  $labor
     * @param  list<array{string, int, string}>  $partRows  sku, quantity, revenue — in the report's order
     */
    private function assertReport(string $step, array $revenue, array $payments, array $labor, string $partsRevenue, array $partRows): void
    {
        $reports = app(ReportService::class);

        $this->assertSame($revenue, $this->only($reports->revenue($this->today, $this->today), array_keys($revenue)), "{$step}: revenue");
        $this->assertSame($payments, $this->paymentRows(), "{$step}: payments");
        $this->assertSame(
            $labor,
            array_map(fn (array $row): array => [$row['user']->getKey(), $row['hours'], $row['amount']], $reports->laborByMechanic($this->today, $this->today)),
            "{$step}: labor by mechanic",
        );

        $parts = $reports->partsSold($this->today, $this->today, false);
        $this->assertSame($partsRevenue, $parts['revenue'], "{$step}: parts revenue");
        $this->assertSame(
            $partRows,
            array_map(fn (array $row): array => [$row['part']->sku, $row['quantity'], $row['revenue']], $parts['rows']),
            "{$step}: parts rows",
        );
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    private function assertRefused(string $label, string $exception, Closure $attempt): void
    {
        try {
            $attempt();
        } catch (Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown, "{$label}: refused with the wrong exception ({$thrown->getMessage()}).");

            return;
        }

        $this->fail("{$label} was not refused.");
    }

    // ----- sequence C -----

    private function updateVehicleForm(Vehicle $vehicle, string $nextServiceOn): void
    {
        $current = $vehicle->fresh();

        $this->actingAs($this->desk)
            ->put(route('vehicles.update', $current), [
                'registration_plate' => $current->registration_plate,
                'make' => $current->make,
                'model' => $current->model,
                'year' => $current->year,
                'vin' => $current->vin,
                'engine' => $current->engine,
                'mileage' => $current->mileage,
                'next_service_on' => $nextServiceOn,
                'next_service_mileage' => $current->next_service_mileage,
                'registration_expires_on' => $current->registration_expires_on?->toDateString(),
                'notes' => $current->notes,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vehicles.show', $current));

        $this->assertSame($nextServiceOn, $vehicle->fresh()->next_service_on->toDateString());
    }

    /**
     * One command run and spec 36's invariant for sequence C: as many reminder
     * rows as queued reminders, and no two rows for one (vehicle, kind, due_on).
     *
     * The duplicate check cannot fail while the unique index on those three
     * columns exists — it guards against the index being dropped, nothing more.
     * The same index is why "a second run queues nothing" is asserted on the
     * ATTEMPTED inserts: with the command's "not already reminded" filter
     * removed, the duplicate insert is refused by the index and caught, so rows
     * and mails still agree. DB::getQueryLog() never shows a refused statement
     * (.ai/rules/tests.md), so DB::beforeExecuting() collects them instead.
     */
    private function runReminders(string $step, int $inserts): void
    {
        $attempted = 0;
        $listening = true;

        DB::beforeExecuting(function (string $sql) use (&$attempted, &$listening): void {
            if ($listening && str_starts_with($sql, 'insert into `vehicle_reminders`')) {
                $attempted++;
            }
        });

        try {
            $this->artisan('reminders:send')->assertSuccessful();
        } finally {
            $listening = false;
        }

        $this->assertSame($inserts, $attempted, "{$step}: the run attempted the wrong number of reminder inserts.");

        $rows = DB::table('vehicle_reminders')->count();
        $this->assertSame(Mail::queued(ServiceReminderMail::class)->count(), $rows, "{$step}: reminder rows and queued reminders disagree.");
        $this->assertSame(
            [],
            DB::table('vehicle_reminders')->select(['vehicle_id', 'kind', 'due_on'])->groupBy('vehicle_id', 'kind', 'due_on')->havingRaw('COUNT(*) > 1')->get()->all(),
            "{$step}: two reminders share a vehicle, kind and due date.",
        );
    }

    /**
     * @param  list<array{int, string, string, int}>  $rows
     * @param  list<string>  $recipients
     */
    private function assertReminders(string $step, array $rows, array $recipients): void
    {
        $this->assertSame(
            $rows,
            DB::table('vehicle_reminders')->orderBy('id')->get()->map(fn (object $row): array => [(int) $row->vehicle_id, $row->kind, CarbonImmutable::parse($row->due_on)->toDateString(), (int) $row->customer_id])->all(),
            "{$step}: reminder rows",
        );

        $this->assertSame(
            $recipients,
            Mail::queued(ServiceReminderMail::class)->map(fn (ServiceReminderMail $mail): string => $mail->to[0]['address'])->values()->all(),
            "{$step}: reminder recipients",
        );
    }

    /**
     * The receptionist's due-soon row for V. In the emailed state no phone is
     * shown at all; in the call state the current owner's phone is, and the
     * other customer's phone never is.
     */
    private function assertDueSoonRow(string $step, Vehicle $vehicle, string $contact, string $ownerPhone, string $otherPhone): void
    {
        $response = $this->actingAs($this->desk)->get(route('dashboard'))->assertOk();

        $rows = array_values(array_filter(
            $response->viewData('dueSoon'),
            fn (array $row): bool => $row['vehicle']->getKey() === $vehicle->getKey(),
        ));

        $this->assertCount(1, $rows, "{$step}: the panel lists V once.");
        $this->assertSame($contact, $rows[0]['contact'], "{$step}: contact state");

        if ($contact === 'emailed') {
            $response->assertSee(__('dashboard.due_soon.emailed', ['date' => $this->today->isoFormat('D MMM')]));
            $response->assertDontSee($ownerPhone);
        } else {
            $this->assertSame($ownerPhone, $rows[0]['phone'], "{$step}: the panel shows the current owner's phone.");
            $response->assertSee($ownerPhone);
        }

        $response->assertDontSee($otherPhone);
    }
}
