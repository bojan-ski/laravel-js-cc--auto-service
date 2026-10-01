<?php

namespace Tests\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;

/**
 * Report fixtures are issued through InvoiceService::issue() at a chosen
 * moment, never built by the invoice factory, so every stored figure is the
 * one the application computed from the job's lines. The report's invariants
 * only hold for invoices made that way.
 */
trait BuildsReportFixtures
{
    private ?User $reportIssuer = null;

    protected function completedJob(?Customer $customer = null): Job
    {
        return Job::factory()->completed()->for($customer ?? Customer::factory()->create())->create();
    }

    protected function laborLine(Job $job, User $mechanic, string $hours, string $price, string $discount = '0.00'): JobItem
    {
        return JobItem::factory()->forJob($job)->performedBy($mechanic)->create([
            'quantity' => $hours,
            'unit_price' => $price,
            'discount_percent' => $discount,
        ]);
    }

    protected function partLine(Job $job, Part $part, int $quantity, string $price, string $discount = '0.00'): JobItem
    {
        return JobItem::factory()->forJob($job)->forPart($part)->create([
            'user_id' => null,
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount_percent' => $discount,
        ]);
    }

    protected function issueAt(Job $job, string $moment): Invoice
    {
        $this->travelTo(CarbonImmutable::parse($moment));

        return app(InvoiceService::class)->issue($job, $this->reportIssuer());
    }

    /**
     * One labor line on a fresh job, issued at the given moment.
     */
    protected function invoiceAt(string $moment, string $hours = '1.00', string $price = '100.00', ?User $mechanic = null): Invoice
    {
        $job = $this->completedJob();
        $this->laborLine($job, $mechanic ?? User::factory()->mechanic()->create(), $hours, $price);

        return $this->issueAt($job, $moment);
    }

    protected function payAt(Invoice $invoice, string $moment, PaymentMethod $method): Invoice
    {
        $this->travelTo(CarbonImmutable::parse($moment));

        return app(InvoiceService::class)->markPaid($invoice, $method);
    }

    /**
     * An invoice paid before spec 28 has no method. markPaid() now refuses
     * that, so the row is written the way those invoices were.
     */
    protected function payWithoutMethod(Invoice $invoice): Invoice
    {
        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = $invoice->issued_at;
        $invoice->save();

        return $invoice;
    }

    protected function voidAt(Invoice $invoice, string $moment): Invoice
    {
        $this->travelTo(CarbonImmutable::parse($moment));

        return app(InvoiceService::class)->void($invoice, User::factory()->admin()->create());
    }

    private function reportIssuer(): User
    {
        return $this->reportIssuer ??= User::factory()->receptionist()->create();
    }
}
