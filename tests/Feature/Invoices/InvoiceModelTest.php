<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidInvoiceTransition;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creating_an_invoice_already_void_is_permitted(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->assertTrue($invoice->fresh()->isVoid());
    }

    public function test_setting_the_status_to_void_is_permitted(): void
    {
        $invoice = Invoice::factory()->unpaid()->create();
        $invoice->status = InvoiceStatus::Void;
        $invoice->voided_at = now();
        $invoice->save();

        $this->assertTrue($invoice->fresh()->isVoid());
    }

    public function test_any_write_to_a_void_invoice_throws(): void
    {
        $invoice = Invoice::factory()->void()->create();
        $invoice->status = InvoiceStatus::Paid;

        try {
            $invoice->save();
            $this->fail('Expected InvalidInvoiceTransition.');
        } catch (InvalidInvoiceTransition $exception) {
            $this->assertTrue($exception->invoice->is($invoice));
            $this->assertSame(['status'], array_keys($exception->changes));
        }

        $this->assertTrue($invoice->fresh()->isVoid());
    }

    public function test_the_guard_is_not_specific_to_the_status_column(): void
    {
        $invoice = Invoice::factory()->void()->create();
        $invoice->due_at = now()->addWeek()->startOfDay();

        try {
            $invoice->save();
            $this->fail('Expected InvalidInvoiceTransition.');
        } catch (InvalidInvoiceTransition $exception) {
            $this->assertSame(['due_at'], array_keys($exception->changes));
        }

        $this->assertNull($invoice->fresh()->due_at);
    }

    public function test_the_guard_fires_on_touch_of_a_void_invoice(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->travel(1)->minute();

        $this->expectException(InvalidInvoiceTransition::class);

        $invoice->touch();
    }

    public function test_an_unpaid_invoice_is_unaffected_by_the_guard(): void
    {
        $invoice = Invoice::factory()->unpaid()->create();
        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = now();
        $invoice->save();

        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_an_invoice_loaded_without_its_status_column_refuses_to_save(): void
    {
        $invoice = Invoice::factory()->unpaid()->create();
        $partial = Invoice::query()->select(['id', 'due_at'])->findOrFail($invoice->getKey());
        $partial->due_at = now()->addWeek();

        $this->expectException(InvalidInvoiceTransition::class);

        try {
            $partial->save();
        } finally {
            $this->assertNull($invoice->fresh()->due_at);
        }
    }

    public function test_an_invoice_loaded_with_its_status_column_still_saves_when_not_void(): void
    {
        $invoice = Invoice::factory()->unpaid()->create();
        $partial = Invoice::query()->select(['id', 'status', 'due_at'])->findOrFail($invoice->getKey());
        $partial->due_at = now()->addWeek()->startOfDay();
        $partial->save();

        $this->assertNotNull($invoice->fresh()->due_at);
    }

    /**
     * Every invoice column is server-controlled, so none may be mass assigned.
     *
     * This guards the regression that matters — someone adding columns to
     * `#[Fillable]`. It cannot distinguish an empty `#[Fillable([])]` from no
     * attribute at all, because Eloquent already guards everything by default;
     * those two states are identical, so no test can tell them apart.
     */
    public function test_no_invoice_column_is_mass_assignable(): void
    {
        $columns = [
            'job_id', 'number', 'status', 'subtotal', 'tax_rate', 'tax_amount',
            'total', 'issued_at', 'due_at', 'paid_at', 'payment_method', 'voided_at', 'voided_by', 'issued_by',
        ];

        foreach ($columns as $column) {
            $this->assertFalse(
                (new Invoice)->isFillable($column),
                "Expected {$column} to be guarded against mass assignment.",
            );
        }
    }

    public function test_the_issuer_resolves_even_when_soft_deleted(): void
    {
        $issuer = User::factory()->receptionist()->create();
        $invoice = Invoice::factory()->create(['issued_by' => $issuer->getKey()]);

        $issuer->delete();

        $this->assertTrue($invoice->fresh()->issuedBy->is($issuer));
    }

    public function test_the_status_predicates_agree_with_the_stored_status(): void
    {
        $unpaid = Invoice::factory()->unpaid()->create();
        $paid = Invoice::factory()->paid()->create();
        $void = Invoice::factory()->void()->create();

        $this->assertTrue($unpaid->isUnpaid());
        $this->assertFalse($unpaid->isPaid());
        $this->assertFalse($unpaid->isVoid());

        $this->assertTrue($paid->isPaid());
        $this->assertFalse($paid->isUnpaid());

        $this->assertTrue($void->isVoid());
        $this->assertFalse($void->isUnpaid());
    }

    public function test_only_an_unpaid_invoice_can_be_marked_paid(): void
    {
        $this->assertTrue(Invoice::factory()->unpaid()->create()->canBeMarkedPaid());
        $this->assertFalse(Invoice::factory()->paid()->create()->canBeMarkedPaid());
        $this->assertFalse(Invoice::factory()->void()->create()->canBeMarkedPaid());
    }

    /**
     * A void invoice keeps the paid date it had before voiding, so wasPaid()
     * must read the date rather than the status; isPaid() is false for both
     * void cases and could not tell them apart.
     */
    public function test_was_paid_follows_the_paid_date_not_the_status(): void
    {
        $this->assertFalse(Invoice::factory()->unpaid()->create()->wasPaid());
        $this->assertTrue(Invoice::factory()->paid()->create()->wasPaid());
        $this->assertFalse(Invoice::factory()->void()->create()->wasPaid());
        $this->assertTrue(Invoice::factory()->void()->create(['paid_at' => now()->subDay()])->wasPaid());
    }

    public function test_the_payment_method_is_cast_to_its_enum(): void
    {
        $invoice = Invoice::factory()->paid()->create(['payment_method' => PaymentMethod::BankTransfer]);

        $this->assertSame(PaymentMethod::BankTransfer, $invoice->fresh()->payment_method);
        $this->assertNull(Invoice::factory()->unpaid()->create()->fresh()->payment_method);
    }

    #[DataProvider('taxRates')]
    public function test_the_tax_rate_label_drops_only_trailing_zeros(string $stored, string $expected): void
    {
        $invoice = Invoice::factory()->create(['tax_rate' => $stored]);

        $this->assertSame($expected, $invoice->taxRateLabel());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function taxRates(): array
    {
        return [
            'whole number' => ['20.00', '20'],
            'one decimal' => ['17.50', '17.5'],
            'two decimals' => ['20.10', '20.1'],
            'both decimals significant' => ['12.60', '12.6'],
            'zero' => ['0.00', '0'],
        ];
    }
}
