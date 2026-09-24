<?php

namespace Tests\Feature\Mail;

use App\Enums\Locale;
use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CustomerMailer;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Customer email is written in the shop's language, never in the language of
 * whoever happened to press Complete or Issue. The staff member's locale is
 * set to something else in every test here, so a send that inherited it would
 * be caught.
 */
class CustomerMailLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.locale' => 'de']);
        App::setLocale('sr');
    }

    public function test_a_customer_mail_is_queued_in_the_shop_language(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        app(CustomerMailer::class)->send($customer, new JobCompletedMail($job, $customer, $vehicle));

        Mail::assertQueued(JobCompletedMail::class, fn (JobCompletedMail $mail): bool => $mail->locale === 'de');
    }

    public function test_the_job_ready_mail_renders_in_the_shop_language(): void
    {
        $this->assertNotSame(__('mail.job_completed.heading', [], 'en'), __('mail.job_completed.heading', [], 'de'));

        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        app(CustomerMailer::class)->send($customer, new JobCompletedMail($job, $customer, $vehicle));

        $mail = Mail::queued(JobCompletedMail::class)->first();
        $html = $mail->render();

        $this->assertStringContainsString(e(__('mail.job_completed.heading', [], 'de')), $html);
        $this->assertStringNotContainsString(e(__('mail.job_completed.heading', [], 'sr')), $html);
        $this->assertStringContainsString('<html lang="de">', $html);
        $this->assertSame('sr', App::getLocale(), 'Rendering the mail must not leave the request in the shop locale.');
    }

    /**
     * The issue date is written with a month name, which only follows the
     * locale through translatedFormat(); format() would print it in English.
     */
    public function test_the_invoice_mail_writes_its_dates_in_the_shop_language(): void
    {
        $this->travelTo('2026-10-15 10:00');

        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => '1000.00']);

        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create(), now()->addDays(14));

        $mail = Mail::queued(InvoiceIssuedMail::class)->first();

        $this->assertNotNull($mail);
        $this->assertSame('de', $mail->locale);

        $html = $mail->render();
        $german = now()->locale('de')->translatedFormat('j M Y');

        $this->assertNotSame(now()->locale('en')->translatedFormat('j M Y'), $german, 'The sample date must differ between English and German.');
        $this->assertStringContainsString($german, $html);
    }

    public function test_the_shop_locale_falls_back_to_english_when_misconfigured(): void
    {
        config(['shop.locale' => 'klingon']);

        $this->assertSame(Locale::En, Locale::shop());
    }
}
