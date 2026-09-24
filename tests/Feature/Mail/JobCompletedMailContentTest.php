<?php

namespace Tests\Feature\Mail;

use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

class JobCompletedMailContentTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private function mailable(): JobCompletedMail
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrovic']);
        $vehicle = Vehicle::factory()->for($customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'VW',
            'model' => 'Golf IV',
        ]);
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        return new JobCompletedMail($job, $customer, $vehicle);
    }

    public function test_the_subject_names_the_plate(): void
    {
        $this->mailable()->assertHasSubject(__('mail.job_completed.subject', ['plate' => 'BG123-ZZ']));
    }

    public function test_both_bodies_carry_the_customer_vehicle_and_reference(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrovic']);
        $vehicle = Vehicle::factory()->for($customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'VW',
            'model' => 'Golf IV',
        ]);
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        $mailable = new JobCompletedMail($job, $customer, $vehicle);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('Milan Petrovic', false);
            $mailable->{$assertion}('VW Golf IV (BG123-ZZ) is ready for collection.', false);
        }

        $mailable->assertSeeInText('Job reference: '.$job->reference, false);
        $this->assertHtmlRow($mailable, 'Job reference', $job->reference);
        $mailable->assertSeeInHtml('>Your vehicle is ready</h1>', false);
        $mailable->assertSeeInOrderInHtml([__('mail.vehicle'), 'BG123-ZZ', 'VW Golf IV'], false);
    }

    /**
     * Reading the expected value from the same config() call the view uses
     * would pass even if the footer hard-coded the shipped defaults, which are
     * byte-identical to them. Sentinels that appear nowhere in config/shop.php
     * are the only thing that proves the lines are config-driven.
     */
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

    public function test_the_footer_lines_render_in_order_with_no_gaps(): void
    {
        config([
            'shop.identity.name' => 'ZZ-NAME',
            'shop.identity.address' => 'ZZ-ADDRESS',
            'shop.identity.phone' => 'ZZ-PHONE',
            'shop.identity.tax_number' => 'ZZ-TAX',
        ]);

        $this->mailable()->assertSeeInText("ZZ-NAME\nZZ-ADDRESS\nZZ-PHONE\nZZ-TAX", false);
    }

    public function test_a_blank_footer_line_is_omitted_rather_than_rendered_empty(): void
    {
        config(['shop.identity.phone' => '']);

        $this->mailable()->assertSeeInText(implode("\n", [
            config('shop.identity.address'),
            config('shop.identity.tax_number'),
        ]), false);
    }

    public function test_the_message_carries_no_attachment(): void
    {
        $this->assertFalse(method_exists(JobCompletedMail::class, 'attachments'));
    }

    /**
     * Asserts the rendered FIGURES and the invoice number, never loose words
     * like "Price" — Faker generates that as a surname, which is the flake
     * fixed on fix/price-header-test-flake.
     */
    public function test_the_message_carries_no_price_no_total_and_no_invoice_reference(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 2, 'unit_price' => '5300.00']);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $this->assertSame('10600.00', $invoice->subtotal);
        $this->assertSame('12720.00', $invoice->total);

        $mailable = new JobCompletedMail($job, $customer, $vehicle);

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $assertion) {
            $mailable->{$assertion}('5,300.00', false);
            $mailable->{$assertion}('10,600.00', false);
            $mailable->{$assertion}('2,120.00', false);
            $mailable->{$assertion}('12,720.00', false);
            $mailable->{$assertion}($invoice->number, false);
        }
    }

    /**
     * Every key, not one. Asserting a literal English string, or recomputing
     * the expectation with the same __() call the view makes, both pass against
     * a view that hard-codes the current copy.
     */
    public function test_every_sentence_resolves_through_the_translator(): void
    {
        Lang::addLines([
            'mail.vehicle' => 'ZZ-VEHICLE',
            'mail.pair' => 'ZZ-PAIR :label=:value',
            'mail.job_completed.subject' => 'ZZ-SUBJECT :plate',
            'mail.job_completed.heading' => 'ZZ-HEADING',
            'mail.job_completed.greeting' => 'ZZ-GREETING :name',
            'mail.job_completed.body' => 'ZZ-BODY :make :model :plate',
            'mail.job_completed.reference' => 'ZZ-REFERENCE',
        ], 'en');

        $mailable = $this->mailable();

        $mailable->assertHasSubject('ZZ-SUBJECT BG123-ZZ');

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('ZZ-GREETING Milan Petrovic', false);
            $mailable->{$assertion}('ZZ-BODY VW Golf IV BG123-ZZ', false);
        }

        $mailable->assertSeeInHtml('ZZ-HEADING', false);
        $mailable->assertSeeInHtml('ZZ-VEHICLE', false);
        $this->assertHtmlRow($mailable, 'ZZ-REFERENCE', $mailable->job->reference);
        $mailable->assertSeeInText('ZZ-PAIR ZZ-REFERENCE='.$mailable->job->reference, false);
    }
}
