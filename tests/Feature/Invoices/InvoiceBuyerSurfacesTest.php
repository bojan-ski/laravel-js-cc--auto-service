<?php

namespace Tests\Feature\Invoices;

use App\Mail\InvoiceIssuedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

/**
 * Every invoice surface prints the buyer as it was at issue. The customer is
 * edited after issuing, so each assertion has two halves: the old value is
 * present and the new one appears nowhere.
 */
class InvoiceBuyerSurfacesTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private const OLD_NAME = 'Stara Firma d.o.o.';

    private const OLD_ADDRESS = 'Stara ulica 1, Beograd';

    private const TAX_ID = '101234569';

    private const REGISTRATION_NUMBER = '01234567';

    private const NEW_NAME = 'Preimenovana Firma';

    private const NEW_ADDRESS = 'Nova ulica 99, Novi Sad';

    private Customer $customer;

    private Vehicle $vehicle;

    private Invoice $invoice;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->receptionist = User::factory()->receptionist()->create();
        $this->customer = Customer::factory()->create([
            'name' => self::OLD_NAME,
            'address' => self::OLD_ADDRESS,
            'tax_id' => self::TAX_ID,
            'registration_number' => self::REGISTRATION_NUMBER,
        ]);
        $this->vehicle = Vehicle::factory()->for($this->customer)->create();

        $job = Job::factory()->completed()->for($this->customer)->for($this->vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 1000]);

        $this->invoice = app(InvoiceService::class)->issue($job, $this->receptionist);

        $this->customer->update([
            'name' => self::NEW_NAME,
            'address' => self::NEW_ADDRESS,
            'tax_id' => null,
            'registration_number' => null,
        ]);
    }

    private function assertNothingNew(string $content): void
    {
        $this->assertStringNotContainsString(self::NEW_NAME, $content);
        $this->assertStringNotContainsString(self::NEW_ADDRESS, $content);
    }

    public function test_the_detail_page_names_the_buyer_as_issued_with_its_company_rows(): void
    {
        $html = $this->actingAs($this->receptionist)->get(route('invoices.show', $this->invoice))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<dd><a href="'.preg_quote(route('customers.show', $this->customer), '/').'"[^>]*>\s*'.preg_quote(e(self::OLD_NAME), '/').'\s*<\/a>/',
            $html,
        );

        foreach (['tax_id' => self::TAX_ID, 'registration_number' => self::REGISTRATION_NUMBER] as $key => $value) {
            $this->assertMatchesRegularExpression(
                '/<dt[^>]*>'.preg_quote(e(__('invoices.buyer.'.$key)), '/').'<\/dt>\s*<dd class="tabular-nums">'.$value.'<\/dd>/',
                $html,
            );
        }

        $this->assertNothingNew($html);
    }

    public function test_the_print_block_bills_the_buyer_as_issued(): void
    {
        $html = $this->actingAs($this->receptionist)->get(route('invoices.show', $this->invoice))->getContent();

        $this->assertMatchesRegularExpression(
            '/<p class="font-medium">'.preg_quote(e(self::OLD_NAME), '/').'<\/p>\s*<p>'.preg_quote(e(self::OLD_ADDRESS), '/').'<\/p>\s*'
            .'<p>'.preg_quote(e(__('invoices.buyer.tax_id')), '/').' <span class="tabular-nums">'.self::TAX_ID.'<\/span><\/p>\s*'
            .'<p>'.preg_quote(e(__('invoices.buyer.registration_number')), '/').' <span class="tabular-nums">'.self::REGISTRATION_NUMBER.'<\/span><\/p>/',
            $html,
        );
    }

    public function test_the_list_row_shows_the_buyer_as_issued(): void
    {
        $html = $this->actingAs($this->receptionist)->get(route('invoices.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(self::OLD_NAME), $html);
        $this->assertNothingNew($html);
    }

    public function test_search_finds_the_name_it_was_issued_to_and_not_the_new_one(): void
    {
        $this->actingAs($this->receptionist);

        $this->get(route('invoices.index', ['search' => 'Stara Firma']))
            ->assertOk()
            ->assertSee($this->invoice->number);

        $this->get(route('invoices.index', ['search' => 'Preimenovana']))
            ->assertOk()
            ->assertDontSee($this->invoice->number);
    }

    public function test_the_email_greets_and_details_the_buyer_as_issued(): void
    {
        $mailable = new InvoiceIssuedMail($this->invoice->fresh(), $this->customer->fresh(), $this->vehicle);

        $greeting = __('mail.invoice_issued.greeting', ['name' => self::OLD_NAME]);
        $mailable->assertSeeInHtml($greeting, false);
        $mailable->assertSeeInText($greeting, false);

        $this->assertHtmlRow($mailable, __('mail.invoice_issued.tax_id'), self::TAX_ID);
        $this->assertHtmlRow($mailable, __('mail.invoice_issued.registration_number'), self::REGISTRATION_NUMBER);
        $mailable->assertSeeInText(__('mail.invoice_issued.tax_id').': '.self::TAX_ID, false);
        $mailable->assertSeeInText(__('mail.invoice_issued.registration_number').': '.self::REGISTRATION_NUMBER, false);

        $mailable->assertDontSeeInHtml(self::NEW_NAME, false);
        $mailable->assertDontSeeInText(self::NEW_NAME, false);
    }

    public function test_the_company_rows_sit_between_the_number_and_the_issue_date_in_the_email(): void
    {
        $mailable = new InvoiceIssuedMail($this->invoice->fresh(), $this->customer->fresh(), $this->vehicle);

        $mailable->assertSeeInOrderInText([
            __('mail.invoice_issued.number').': ',
            __('mail.invoice_issued.tax_id').': ',
            __('mail.invoice_issued.registration_number').': ',
            __('mail.invoice_issued.issued').': ',
        ], false);
        $mailable->assertSeeInOrderInHtml([
            e(__('mail.invoice_issued.number')),
            e(__('mail.invoice_issued.tax_id')),
            e(__('mail.invoice_issued.registration_number')),
            e(__('mail.invoice_issued.issued')),
        ], false);
    }

    public function test_a_private_buyer_gets_no_company_rows_on_any_surface(): void
    {
        $private = Customer::factory()->create(['name' => 'Privatni Kupac']);
        $vehicle = Vehicle::factory()->for($private)->create();
        $job = Job::factory()->completed()->for($private)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 500]);
        $invoice = app(InvoiceService::class)->issue($job, $this->receptionist);

        $html = $this->actingAs($this->receptionist)->get(route('invoices.show', $invoice))->assertOk()->getContent();

        $this->assertStringNotContainsString(e(__('invoices.buyer.tax_id')), $html);
        $this->assertStringNotContainsString(e(__('invoices.buyer.registration_number')), $html);

        $mailable = new InvoiceIssuedMail($invoice->fresh(), $private, $vehicle);
        $this->assertNoHtmlRowLabelled($mailable, __('mail.invoice_issued.tax_id'));
        $this->assertNoHtmlRowLabelled($mailable, __('mail.invoice_issued.registration_number'));
        $mailable->assertDontSeeInText(__('mail.invoice_issued.tax_id'), false);
    }

    public function test_a_trashed_buyer_is_named_as_issued_with_the_deleted_marker_and_no_link(): void
    {
        $this->customer->delete();

        $html = $this->actingAs($this->receptionist)->get(route('invoices.show', $this->invoice))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<dd><span>\s*'.preg_quote(e(self::OLD_NAME), '/').'\s*<span class="text-sm text-text-muted">\('.preg_quote(e(__('app.deleted')), '/').'\)<\/span>/',
            $html,
        );
        $this->assertStringNotContainsString(route('customers.show', $this->customer), $html);
        $this->assertNothingNew($html);
    }
}
