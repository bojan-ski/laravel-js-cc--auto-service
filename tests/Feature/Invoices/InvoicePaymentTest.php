<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidInvoiceTransition;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\CustomerMailer;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function frontOfficeRoles(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    private function issued(): Invoice
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, array{PaymentMethod}>
     */
    public static function paymentMethods(): array
    {
        return [
            'cash' => [PaymentMethod::Cash],
            'card' => [PaymentMethod::Card],
            'bank transfer' => [PaymentMethod::BankTransfer],
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function refusedPayloads(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['payment_method' => '']],
            'unknown' => [['payment_method' => 'bitcoin']],
        ];
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_can_mark_an_unpaid_invoice_paid(string $role): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->{$role}()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'card'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors();

        $fresh = $invoice->fresh();

        $this->assertSame(InvoiceStatus::Paid, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame(PaymentMethod::Card, $fresh->payment_method);
    }

    #[DataProvider('paymentMethods')]
    public function test_each_method_is_recorded_and_named_in_the_flash(PaymentMethod $method): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => $method->value])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('success', __('invoices.flash.paid', [
                'number' => $invoice->number,
                'method' => $method->label(),
            ]));

        $this->assertSame($method, $invoice->fresh()->payment_method);
    }

    public function test_the_flash_names_the_method_in_words(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'bank_transfer']);

        $this->assertStringEndsWith('Payment method: Bank transfer.', session('success'));
    }

    #[DataProvider('refusedPayloads')]
    public function test_a_missing_or_unknown_method_is_refused_and_nothing_is_written(array $payload): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.paid', $invoice), $payload)
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors('payment_method');

        $fresh = $invoice->fresh();

        $this->assertSame(InvoiceStatus::Unpaid, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertNull($fresh->payment_method);
    }

    public function test_a_missing_method_reports_the_shop_message(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice))
            ->assertSessionHasErrors(['payment_method' => __('invoices.validation.payment_method_required')]);
    }

    /**
     * Payment settles a receivable; it does not make the work any more or less
     * final. Unlocking on payment would let a paid job's lines change underneath
     * an invoice the customer has already settled.
     */
    public function test_marking_paid_never_touches_the_jobs_lock(): void
    {
        $invoice = $this->issued();
        $lockedAt = $invoice->job->fresh()->locked_at;

        $this->assertNotNull($lockedAt);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors();

        $this->assertTrue($invoice->fresh()->isPaid(), 'The request must actually mark the invoice paid.');

        $after = $invoice->job->fresh()->locked_at;

        $this->assertNotNull($after, 'Marking paid must not unlock the job.');
        $this->assertTrue($lockedAt->equalTo($after), 'The lock timestamp must be untouched.');
    }

    public function test_a_paid_invoice_cannot_be_marked_paid_again(): void
    {
        $invoice = $this->issued();
        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = now();
        $invoice->payment_method = PaymentMethod::Cash;
        $invoice->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'card'])
            ->assertForbidden();

        $this->assertSame(PaymentMethod::Cash, $invoice->fresh()->payment_method);
    }

    public function test_a_void_invoice_can_never_be_marked_paid(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])
            ->assertForbidden();

        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertNull($invoice->fresh()->payment_method);
    }

    public function test_a_mechanic_sending_a_valid_method_is_refused_at_the_gate(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])
            ->assertForbidden();

        $this->assertNull($invoice->fresh()->payment_method);
    }

    public function test_the_control_is_present_when_payable_and_absent_when_not(): void
    {
        $unpaid = $this->issued();
        $void = Invoice::factory()->void()->create();
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)
            ->get(route('invoices.show', $unpaid))
            ->assertOk()
            ->assertSee('action="'.route('invoices.paid', $unpaid).'"', false)
            ->assertSee(__('invoices.show.mark_paid'));

        $this->actingAs($user)
            ->get(route('invoices.show', $void))
            ->assertOk()
            ->assertDontSee('action="'.route('invoices.paid', $void).'"', false)
            ->assertDontSee(__('invoices.show.mark_paid'));
    }

    private function methodSelect(string $html): DOMElement
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        $select = (new DOMXPath($dom))->query("//form[@action='".route('invoices.paid', Invoice::query()->sole())."']//select[@name='payment_method']")->item(0);

        $this->assertInstanceOf(DOMElement::class, $select, 'The payment method select did not render inside the mark-paid form.');

        return $select;
    }

    /**
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private function optionsOf(DOMElement $select): array
    {
        $options = [];

        foreach ($select->getElementsByTagName('option') as $option) {
            $options[] = [
                'value' => $option->getAttribute('value'),
                'label' => trim($option->textContent),
                'selected' => $option->hasAttribute('selected'),
            ];
        }

        return $options;
    }

    public function test_the_select_is_labelled_required_and_lists_the_methods_after_an_empty_choice(): void
    {
        $invoice = $this->issued();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('<label for="payment_method" class="block text-sm font-medium">'.__('invoices.show.payment_method').'</label>', false)
            ->getContent();

        $select = $this->methodSelect($html);

        $this->assertTrue($select->hasAttribute('required'));
        $this->assertSame([
            ['value' => '', 'label' => __('invoices.show.payment_method_placeholder'), 'selected' => false],
            ['value' => 'cash', 'label' => 'Cash', 'selected' => false],
            ['value' => 'card', 'label' => 'Card', 'selected' => false],
            ['value' => 'bank_transfer', 'label' => 'Bank transfer', 'selected' => false],
        ], $this->optionsOf($select));
    }

    /**
     * Rendered after the redirect without asserting the session first:
     * assertSessionHasErrors() consumes the flashed errors (.ai/rules/tests.md),
     * so the page would render as though validation had passed.
     */
    public function test_a_refused_submit_shows_the_error_under_the_select_and_selects_nothing(): void
    {
        $invoice = $this->issued();
        $show = route('invoices.show', $invoice);

        $this->actingAs(User::factory()->receptionist()->create())
            ->from($show)
            ->patch(route('invoices.paid', $invoice), ['payment_method' => ''])
            ->assertRedirect($show);

        $html = $this->get($show)->assertOk()->getContent();
        $select = $this->methodSelect($html);

        $this->assertSame('true', $select->getAttribute('aria-invalid'));
        $this->assertSame('payment_method-error', $select->getAttribute('aria-describedby'));
        $this->assertStringContainsString(
            '<p id="payment_method-error" class="mt-1 text-sm text-status-cancelled">'.__('invoices.validation.payment_method_required').'</p>',
            $html,
        );
        $this->assertSame([], array_filter(array_column($this->optionsOf($select), 'selected')));
    }

    public function test_the_control_asks_for_confirmation(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('data-confirm="'.e(__('invoices.show.confirm_paid', ['number' => $invoice->number])).'"', false);
    }

    /**
     * WCAG 2.5.3 Label in Name: the aria-label overrides the button's text as
     * its accessible name, so the visible label has to survive inside it as a
     * contiguous substring or speech input cannot address the control. "Mark
     * invoice :number paid" splits the visible words and fails; "Mark paid for
     * invoice :number" does not.
     */
    public function test_the_mark_paid_accessible_name_contains_its_visible_label(): void
    {
        $invoice = $this->issued();

        $visible = __('invoices.show.mark_paid');
        $accessible = __('invoices.show.mark_paid_for', ['number' => $invoice->number]);

        $this->assertStringContainsString($visible, $accessible);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('aria-label="'.e($accessible).'"', false)
            ->assertSee($visible);
    }

    /**
     * The policy composes canBeMarkedPaid(), so a refusal is a 403 at the gate
     * and this exception is only reachable when the status changed between the
     * gate and the lock. That is a lost race, not a bug, so it flashes.
     */
    public function test_a_refusal_from_the_service_redirects_with_an_error_flash(): void
    {
        $invoice = $this->issued();

        $this->app->instance(InvoiceService::class, new RefusingPaymentService);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', __('invoices.flash.not_payable'));

        $this->assertNull($invoice->fresh()->paid_at);
    }

    /**
     * Genuine contention is NOT covered here. LazilyRefreshDatabase wraps each
     * test in a transaction, so the service's own transaction is a savepoint and
     * two real connections never compete — the same wall specs 15 and 16 hit.
     * What is proven: the row is read `for update` before the write, and a
     * sequential second attempt is refused by the re-check under that lock.
     */
    public function test_the_row_is_locked_before_the_write_and_a_second_attempt_is_refused(): void
    {
        $invoice = $this->issued();

        DB::enableQueryLog();
        app(InvoiceService::class)->markPaid($invoice, PaymentMethod::Cash);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lockIndex = null;
        $writeIndex = null;

        foreach ($queries as $index => $query) {
            if ($lockIndex === null && str_contains($query, '`invoices`') && str_contains($query, 'for update')) {
                $lockIndex = $index;
            }

            if ($writeIndex === null && str_starts_with($query, 'update `invoices`')) {
                $writeIndex = $index;
            }
        }

        $this->assertNotNull($lockIndex, "No locking read of invoices:\n".implode("\n", $queries));
        $this->assertNotNull($writeIndex, "No write to invoices:\n".implode("\n", $queries));
        $this->assertLessThan($writeIndex, $lockIndex, 'The row must be locked before it is written.');

        $this->expectException(InvalidInvoiceTransition::class);
        app(InvoiceService::class)->markPaid($invoice->fresh(), PaymentMethod::Card);
    }

    public function test_a_refused_second_attempt_keeps_the_first_method(): void
    {
        $invoice = app(InvoiceService::class)->markPaid($this->issued(), PaymentMethod::Cash);

        $this->assertThrows(
            fn () => app(InvoiceService::class)->markPaid($invoice->fresh(), PaymentMethod::Card),
            InvalidInvoiceTransition::class,
        );

        $this->assertSame(PaymentMethod::Cash, $invoice->fresh()->payment_method);
    }

    public function test_the_returned_instance_carries_the_method_without_a_refetch(): void
    {
        $invoice = $this->issued();

        app(InvoiceService::class)->markPaid($invoice, PaymentMethod::BankTransfer);

        $this->assertSame(PaymentMethod::BankTransfer, $invoice->payment_method);
    }

    public function test_the_service_reads_every_column_under_the_lock(): void
    {
        $invoice = $this->issued();

        DB::enableQueryLog();
        app(InvoiceService::class)->markPaid($invoice, PaymentMethod::Cash);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $locking = array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, '`invoices`') && str_contains($query, 'for update'),
        ));

        $this->assertStringStartsWith('select * from `invoices`', $locking[0], 'A partial select would trip the fail-closed saving guard.');
    }
}

class RefusingPaymentService extends InvoiceService
{
    public function __construct()
    {
        parent::__construct(new InvoiceNumberGenerator, new CustomerMailer);
    }

    public function markPaid(Invoice $invoice, PaymentMethod $method): Invoice
    {
        throw new InvalidInvoiceTransition($invoice, ['status' => 'paid'], 'refused for the test');
    }
}
