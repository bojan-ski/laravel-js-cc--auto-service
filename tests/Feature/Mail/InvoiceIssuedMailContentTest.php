<?php

namespace Tests\Feature\Mail;

use App\Mail\InvoiceIssuedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

class InvoiceIssuedMailContentTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private Customer $customer;

    private Vehicle $vehicle;

    private function issue(?CarbonInterface $dueAt = null, string $unitPrice = '10600.00'): Invoice
    {
        $this->customer = Customer::factory()->create(['name' => 'Milan Petrovic']);
        $this->vehicle = Vehicle::factory()->for($this->customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'VW',
            'model' => 'Golf IV',
        ]);

        $job = Job::factory()->completed()->for($this->customer)->for($this->vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => $unitPrice]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create(), $dueAt);
    }

    private function mailable(?CarbonInterface $dueAt = null, string $unitPrice = '10600.00'): InvoiceIssuedMail
    {
        $invoice = $this->issue($dueAt, $unitPrice);

        return new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);
    }

    public function test_the_subject_names_the_number_and_the_plate(): void
    {
        $invoice = $this->issue();

        (new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle))
            ->assertHasSubject(__('mail.invoice_issued.subject', [
                'number' => $invoice->number,
                'plate' => 'BG123-ZZ',
            ]));
    }

    public function test_both_bodies_carry_the_figures_stored_on_the_invoice(): void
    {
        $invoice = $this->issue();
        $mailable = new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);

        $this->assertSame('10600.00', $invoice->subtotal);
        $this->assertSame('2120.00', $invoice->tax_amount);
        $this->assertSame('12720.00', $invoice->total);

        $mailable->assertSeeInText('Subtotal: 10,600.00', false);
        $mailable->assertSeeInText('VAT 20%: 2,120.00', false);
        $mailable->assertSeeInText('Total: 12,720.00', false);
        $mailable->assertSeeInText('Invoice: '.$invoice->number, false);
        $mailable->assertSeeInText('Issued: '.$invoice->issued_at->format('j M Y'), false);

        $this->assertHtmlRow($mailable, 'Subtotal', '10,600.00');
        $this->assertHtmlRow($mailable, 'VAT 20%', '2,120.00');
        $this->assertHtmlRow($mailable, 'Total', '12,720.00');
        $this->assertHtmlRow($mailable, 'Invoice', $invoice->number);
        $this->assertHtmlRow($mailable, 'Issued', $invoice->issued_at->format('j M Y'));
    }

    public function test_the_html_body_names_the_invoice_and_the_vehicle(): void
    {
        $invoice = $this->issue();
        $mailable = new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);

        $mailable->assertSeeInHtml('>Invoice '.$invoice->number.'</h1>', false);
        $mailable->assertSeeInOrderInHtml([__('mail.vehicle'), 'BG123-ZZ', 'VW Golf IV'], false);
    }

    public function test_a_non_default_rate_is_printed_as_stored_without_trailing_zeros(): void
    {
        config(['shop.vat_rate' => 17.5]);

        $mailable = $this->mailable(unitPrice: '100.00');

        $mailable->assertSeeInText('VAT 17.5%: 17.50', false);
        $this->assertHtmlRow($mailable, 'VAT 17.5%', '17.50');
    }

    public function test_the_due_date_is_rendered_when_it_is_set(): void
    {
        $mailable = $this->mailable(now()->addDays(15));
        $due = now()->addDays(15)->format('j M Y');

        $mailable->assertSeeInText('Due: '.$due, false);
        $this->assertHtmlRow($mailable, 'Due', $due);
    }

    public function test_the_due_line_is_omitted_entirely_when_there_is_no_due_date(): void
    {
        $mailable = $this->mailable();

        $mailable->assertDontSeeInText('Due:', false);
        $this->assertNoHtmlRowLabelled($mailable, 'Due');
    }

    public function test_the_message_carries_no_attachment(): void
    {
        $this->assertFalse(method_exists(InvoiceIssuedMail::class, 'attachments'));

        $mailable = $this->mailable();
        $mailable->render();

        $this->assertSame([], $mailable->attachments);
        $this->assertSame([], $mailable->rawAttachments);
        $this->assertSame([], $mailable->diskAttachments);
    }

    /**
     * The figures must come from the invoice's STORED columns, not from a live
     * recompute over the job's line items. In every fixture the two are equal
     * by construction, so only overwriting storage behind the service's back
     * can tell them apart.
     */
    public function test_the_figures_come_from_storage_rather_than_a_recompute(): void
    {
        $invoice = $this->issue();

        $invoice->forceFill([
            'subtotal' => '1.00',
            'tax_amount' => '2.00',
            'total' => '999.99',
        ])->save();

        $mailable = new InvoiceIssuedMail($invoice->fresh(), $this->customer, $this->vehicle);

        $mailable->assertSeeInText('Total: 999.99', false);
        $mailable->assertSeeInText('Subtotal: 1.00', false);
        $this->assertHtmlRow($mailable, 'Total', '999.99');
        $this->assertHtmlRow($mailable, 'Subtotal', '1.00');

        $mailable->assertDontSeeInText('12,720.00', false);
        $mailable->assertDontSeeInHtml('12,720.00', false);
    }

    public function test_both_bodies_build_every_shop_footer_line_from_config(): void
    {
        config([
            'shop.identity.name' => 'ZZ-NAME',
            'shop.identity.address' => 'ZZ-ADDRESS',
            'shop.identity.phone' => 'ZZ-PHONE',
            'shop.identity.tax_number' => 'ZZ-TAX',
        ]);

        $mailable = $this->mailable();

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('ZZ-NAME', false);
            $mailable->{$assertion}('ZZ-ADDRESS', false);
            $mailable->{$assertion}('ZZ-PHONE', false);
            $mailable->{$assertion}('ZZ-TAX', false);
        }
    }

    public function test_every_sentence_resolves_through_the_translator(): void
    {
        Lang::addLines([
            'mail.vehicle' => 'ZZ-VEHICLE',
            'mail.pair' => 'ZZ-PAIR :label=:value',
            'mail.invoice_issued.subject' => 'ZZ-SUBJECT :number :plate',
            'mail.invoice_issued.heading' => 'ZZ-HEADING :number',
            'mail.invoice_issued.greeting' => 'ZZ-GREETING :name',
            'mail.invoice_issued.body' => 'ZZ-BODY :make :model :plate',
            'mail.invoice_issued.number' => 'ZZ-NUMBER',
            'mail.invoice_issued.issued' => 'ZZ-ISSUED',
            'mail.invoice_issued.due' => 'ZZ-DUE',
            'mail.totals.subtotal' => 'ZZ-SUBTOTAL',
            'mail.totals.vat' => 'ZZ-VAT :rate',
            'mail.totals.total' => 'ZZ-TOTAL',
        ], 'en');

        $invoice = $this->issue(now()->addDays(15));
        $mailable = new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);
        $issued = $invoice->issued_at->format('j M Y');
        $due = $invoice->due_at->format('j M Y');

        $mailable->assertHasSubject('ZZ-SUBJECT '.$invoice->number.' BG123-ZZ');

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('ZZ-GREETING Milan Petrovic', false);
            $mailable->{$assertion}('ZZ-BODY VW Golf IV BG123-ZZ', false);
        }

        $mailable->assertSeeInHtml('ZZ-HEADING '.$invoice->number, false);
        $mailable->assertSeeInHtml('ZZ-VEHICLE', false);

        $this->assertHtmlRow($mailable, 'ZZ-NUMBER', $invoice->number);
        $this->assertHtmlRow($mailable, 'ZZ-ISSUED', $issued);
        $this->assertHtmlRow($mailable, 'ZZ-DUE', $due);
        $this->assertHtmlRow($mailable, 'ZZ-SUBTOTAL', '10,600.00');
        $this->assertHtmlRow($mailable, 'ZZ-VAT 20', '2,120.00');
        $this->assertHtmlRow($mailable, 'ZZ-TOTAL', '12,720.00');

        $mailable->assertSeeInText('ZZ-PAIR ZZ-NUMBER='.$invoice->number, false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-ISSUED='.$issued, false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-DUE='.$due, false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-SUBTOTAL=10,600.00', false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-VAT 20=2,120.00', false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-TOTAL=12,720.00', false);
    }

    public function test_the_discount_rows_resolve_through_the_translator(): void
    {
        Lang::addLines([
            'mail.pair' => 'ZZ-PAIR :label=:value',
            'mail.totals.before_discount' => 'ZZ-BEFORE',
            'mail.totals.discount' => 'ZZ-DISCOUNT',
        ], 'en');

        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->for($this->customer)->create();
        $job = Job::factory()->completed()->for($this->customer)->for($this->vehicle)->create();
        JobItem::factory()->forJob($job)->discounted('10.00')->create(['quantity' => 1, 'unit_price' => '10000.00']);
        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $mailable = new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);

        $this->assertHtmlRow($mailable, 'ZZ-BEFORE', '10,000.00');
        $this->assertHtmlRow($mailable, 'ZZ-DISCOUNT', '1,000.00');
        $mailable->assertSeeInText('ZZ-PAIR ZZ-BEFORE=10,000.00', false);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-DISCOUNT=1,000.00', false);
    }
}
