<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InvoiceDiscountViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function completedJob(bool $discounted): Job
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->discounted($discounted ? '12.50' : '0.00')->create([
            'description' => 'Timing belt replacement',
            'quantity' => '1.00',
            'unit_price' => '1000.00',
        ]);

        return $job;
    }

    private function issued(bool $discounted): Invoice
    {
        return app(InvoiceService::class)->issue($this->completedJob($discounted), User::factory()->receptionist()->create());
    }

    private function totalsBlock(TestResponse $response): string
    {
        $this->assertSame(
            1,
            preg_match('/<dl class="border border-border print:break-inside-avoid">.*?<\/dl>/s', $response->getContent(), $block),
            'The totals block did not render.',
        );

        return $block[0];
    }

    private function assertDiscountRows(TestResponse $response): void
    {
        $block = $this->totalsBlock($response);

        $this->assertMatchesRegularExpression(
            '/'.preg_quote(__('invoices.totals.before_discount'), '/').'.*1,000\.00.*'
            .'>'.preg_quote(__('invoices.totals.discount'), '/').'<.*125\.00.*'
            .preg_quote(__('invoices.totals.subtotal'), '/').'.*875\.00/s',
            $block,
        );
    }

    private function assertNoDiscountRows(TestResponse $response): void
    {
        $block = $this->totalsBlock($response);

        $this->assertStringNotContainsString(__('invoices.totals.before_discount'), $block);
        $this->assertStringNotContainsString('>'.__('invoices.totals.discount').'<', $block);
        $this->assertSame(3, substr_count($block, '<dt'));
    }

    public function test_the_invoice_page_shows_the_discount_column_and_rows(): void
    {
        $invoice = $this->issued(true);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk();

        $this->assertSame(1, preg_match('/<thead\b.*?<\/thead>/s', $response->getContent(), $head));
        $this->assertStringContainsString(__('invoices.items.discount'), $head[0]);
        $response->assertSeeInOrder(['Timing belt replacement', '1,000.00', '12.5%', '875.00']);
        $response->assertDontSee('12.50%');

        $this->assertDiscountRows($response);
    }

    public function test_an_undiscounted_invoice_page_has_no_discount_column_or_rows(): void
    {
        $invoice = $this->issued(false);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk();

        $this->assertSame(1, preg_match('/<thead\b.*?<\/thead>/s', $response->getContent(), $head));
        $this->assertStringNotContainsString(__('invoices.items.discount'), $head[0]);
        $this->assertSame(4, substr_count($head[0], '<th '));

        $this->assertNoDiscountRows($response);
    }

    public function test_a_void_invoice_shows_its_stored_discount_rows_without_line_items(): void
    {
        $invoice = $this->issued(true);
        app(InvoiceService::class)->void($invoice, User::factory()->admin()->create());

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk();

        $this->assertStringNotContainsString('<thead', $response->getContent());
        $this->assertDiscountRows($response);
    }

    public function test_the_issue_page_previews_the_discount_rows_only_when_there_is_one(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->assertDiscountRows(
            $this->actingAs($receptionist)->get(route('jobs.invoice.create', $this->completedJob(true)))->assertOk(),
        );

        $this->assertNoDiscountRows(
            $this->actingAs($receptionist)->get(route('jobs.invoice.create', $this->completedJob(false)))->assertOk(),
        );
    }

    public function test_the_void_confirmation_page_shows_the_discount_rows_only_when_there_is_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertDiscountRows(
            $this->actingAs($admin)->get(route('invoices.void.create', $this->issued(true)))->assertOk(),
        );

        $this->assertNoDiscountRows(
            $this->actingAs($admin)->get(route('invoices.void.create', $this->issued(false)))->assertOk(),
        );
    }
}
