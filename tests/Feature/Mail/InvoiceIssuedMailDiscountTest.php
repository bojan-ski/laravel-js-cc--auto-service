<?php

namespace Tests\Feature\Mail;

use App\Mail\InvoiceIssuedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

class InvoiceIssuedMailDiscountTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private function mailable(string $percent): InvoiceIssuedMail
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->discounted($percent)->create(['quantity' => '1.00', 'unit_price' => '10000.00']);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        return new InvoiceIssuedMail($invoice->fresh(), $customer, $vehicle);
    }

    public function test_the_money_array_carries_the_formatted_discount_figures(): void
    {
        $money = $this->mailable('10.00')->money();

        $this->assertSame('9,000.00', $money['subtotal']);
        $this->assertSame('1,000.00', $money['discount']);
        $this->assertSame('10,000.00', $money['before_discount']);
        $this->assertTrue($money['has_discount']);
    }

    public function test_both_bodies_show_the_discount_rows_before_the_subtotal(): void
    {
        $mailable = $this->mailable('10.00');

        $this->assertHtmlRow($mailable, __('mail.totals.before_discount'), '10,000.00');
        $this->assertHtmlRow($mailable, __('mail.totals.discount'), '1,000.00');
        $this->assertHtmlRow($mailable, __('mail.totals.subtotal'), '9,000.00');

        $mailable->assertSeeInOrderInHtml([
            __('mail.totals.before_discount'),
            __('mail.totals.discount'),
            __('mail.totals.subtotal'),
        ]);

        $mailable->assertSeeInOrderInText([
            __('mail.pair', ['label' => __('mail.totals.before_discount'), 'value' => '10,000.00']),
            __('mail.pair', ['label' => __('mail.totals.discount'), 'value' => '1,000.00']),
            __('mail.pair', ['label' => __('mail.totals.subtotal'), 'value' => '9,000.00']),
        ], false);
    }

    public function test_an_undiscounted_invoice_email_has_no_discount_rows(): void
    {
        $mailable = $this->mailable('0.00');

        $this->assertFalse($mailable->money()['has_discount']);
        $this->assertNoHtmlRowLabelled($mailable, __('mail.totals.before_discount'));
        $this->assertNoHtmlRowLabelled($mailable, __('mail.totals.discount'));
        $this->assertHtmlRow($mailable, __('mail.totals.subtotal'), '10,000.00');

        $mailable->assertDontSeeInText(__('mail.totals.before_discount'), false);
        $mailable->assertDontSeeInText(__('mail.totals.discount').':', false);
    }
}
