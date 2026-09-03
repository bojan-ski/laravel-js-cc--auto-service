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
use Tests\TestCase;

class InvoiceIssuedMailContentTest extends TestCase
{
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

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('Subtotal: 10,600.00', false);
            $mailable->{$assertion}('VAT 20%: 2,120.00', false);
            $mailable->{$assertion}('Total: 12,720.00', false);
            $mailable->{$assertion}('Invoice: '.$invoice->number, false);
            $mailable->{$assertion}('Issued: '.$invoice->issued_at->format('j M Y'), false);
        }
    }

    public function test_a_non_default_rate_is_printed_as_stored_without_trailing_zeros(): void
    {
        config(['shop.vat_rate' => 17.5]);

        $mailable = $this->mailable(unitPrice: '100.00');

        $mailable->assertSeeInText('VAT 17.5%: 17.50', false);
        $mailable->assertSeeInHtml('VAT 17.5%: 17.50', false);
    }

    public function test_the_due_date_is_rendered_when_it_is_set(): void
    {
        $mailable = $this->mailable(now()->addDays(15));

        $mailable->assertSeeInText(__('mail.invoice_issued.due', [
            'date' => now()->addDays(15)->format('j M Y'),
        ]), false);
    }

    public function test_the_due_line_is_omitted_entirely_when_there_is_no_due_date(): void
    {
        $mailable = $this->mailable();

        $mailable->assertDontSeeInText('Due:', false);
        $mailable->assertDontSeeInHtml('Due:', false);
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

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('Total: 999.99', false);
            $mailable->{$assertion}('Subtotal: 1.00', false);
        }

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
            'mail.invoice_issued.subject' => 'ZZ-SUBJECT :number :plate',
            'mail.invoice_issued.greeting' => 'ZZ-GREETING :name',
            'mail.invoice_issued.body' => 'ZZ-BODY :make :model :plate',
            'mail.invoice_issued.number' => 'ZZ-NUMBER :number',
            'mail.invoice_issued.issued' => 'ZZ-ISSUED :date',
            'mail.invoice_issued.due' => 'ZZ-DUE :date',
            'mail.invoice_issued.subtotal' => 'ZZ-SUBTOTAL :amount',
            'mail.invoice_issued.vat' => 'ZZ-VAT :rate :amount',
            'mail.invoice_issued.total' => 'ZZ-TOTAL :amount',
        ], 'en');

        $invoice = $this->issue(now()->addDays(15));
        $mailable = new InvoiceIssuedMail($invoice, $this->customer, $this->vehicle);

        $mailable->assertHasSubject('ZZ-SUBJECT '.$invoice->number.' BG123-ZZ');

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('ZZ-GREETING Milan Petrovic', false);
            $mailable->{$assertion}('ZZ-BODY VW Golf IV BG123-ZZ', false);
            $mailable->{$assertion}('ZZ-NUMBER '.$invoice->number, false);
            $mailable->{$assertion}('ZZ-ISSUED ', false);
            $mailable->{$assertion}('ZZ-DUE ', false);
            $mailable->{$assertion}('ZZ-SUBTOTAL 10,600.00', false);
            $mailable->{$assertion}('ZZ-VAT 20 2,120.00', false);
            $mailable->{$assertion}('ZZ-TOTAL 12,720.00', false);
        }
    }
}
