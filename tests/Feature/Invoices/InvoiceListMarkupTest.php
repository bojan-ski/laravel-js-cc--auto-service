<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoiceListMarkupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoiceFor(string $customerName, string $state = 'unpaid'): Invoice
    {
        $customer = Customer::factory()->create(['name' => $customerName]);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($customer)->for($vehicle)->completed()->create();

        return Invoice::factory()->forJob($job)->{$state}()->create(['total' => '1234.00']);
    }

    private function rowFor(string $html, Invoice $invoice): string
    {
        preg_match_all('/<tr\b.*?<\/tr>/s', $html, $rows);

        foreach ($rows[0] as $row) {
            if (str_contains($row, $invoice->number)) {
                return $row;
            }
        }

        $this->fail("No row rendered for invoice {$invoice->number}.");
    }

    private function list(): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * A void invoice is a record, not a receivable, so its row reads muted and
     * its total is struck through.
     */
    public function test_a_void_row_is_muted_and_its_total_struck_through(): void
    {
        $void = $this->invoiceFor('M. Petrović', 'void');

        $row = $this->rowFor($this->list(), $void);

        $this->assertMatchesRegularExpression('/<tr[^>]*class="[^"]*\btext-text-muted\b/', $row);
        $this->assertMatchesRegularExpression('/<span class="[^"]*\bline-through\b[^"]*">\s*1,234\.00\s*<\/span>/', $row);
    }

    public function test_an_unpaid_row_is_neither_muted_nor_struck_through(): void
    {
        $unpaid = $this->invoiceFor('A. Nikolić');

        $row = $this->rowFor($this->list(), $unpaid);

        $this->assertStringContainsString('1,234.00', $row);
        $this->assertDoesNotMatchRegularExpression('/<tr[^>]*class="[^"]*\btext-text-muted\b/', $row);
        $this->assertStringNotContainsString('line-through', $row);
    }

    public function test_the_number_cell_links_to_the_detail_page(): void
    {
        $invoice = $this->invoiceFor('J. Ilić');

        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('invoices.show', $invoice), '/').'"[^>]*>\s*'
                .preg_quote($invoice->number, '/').'\s*<\/a>/',
            $this->rowFor($this->list(), $invoice),
        );
    }

    /**
     * The customer reaches the row through a historical relation, so a deleted
     * one still renders — as marked text rather than a link into a page whose
     * binding would 404.
     */
    public function test_a_deleted_customer_renders_as_marked_text_without_a_link(): void
    {
        $invoice = $this->invoiceFor('Milica Petrović');
        $customer = $invoice->job->customer;
        $customer->delete();

        $row = $this->rowFor($this->list(), $invoice->fresh());

        $this->assertStringContainsString('Milica Petrović', $row);
        $this->assertStringContainsString(__('app.deleted'), $row);
        $this->assertStringNotContainsString(route('customers.show', $customer), $row);
    }

    public function test_the_payment_route_404s_for_an_unknown_invoice(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('invoices.paid', 4040))
            ->assertNotFound();
    }
}
