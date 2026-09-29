<?php

namespace Tests\Feature\Invoices;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reads the details list as term => value pairs rather than searching the
 * page, because method labels are ordinary words: a customer, a job title or
 * a line description could carry "Cash" or "Card" anywhere else on it.
 */
class InvoicePaymentDetailsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PAID_AT = '2026-09-15 10:00:00';

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function cases(): array
    {
        return [
            'unpaid' => ['unpaid', null],
            'paid' => ['paid', 'Card'],
            'paid before methods were recorded' => ['legacyPaid', ''],
            'void, never paid' => ['voidNeverPaid', null],
            'void, paid before voiding' => ['voidPaid', 'Bank transfer'],
            'void, paid before methods were recorded' => ['voidLegacyPaid', ''],
        ];
    }

    private function unpaid(): Invoice
    {
        return Invoice::factory()->unpaid()->create();
    }

    private function paid(): Invoice
    {
        return Invoice::factory()->paid()->create(['paid_at' => self::PAID_AT, 'payment_method' => PaymentMethod::Card]);
    }

    private function legacyPaid(): Invoice
    {
        return Invoice::factory()->paid()->create(['paid_at' => self::PAID_AT, 'payment_method' => null]);
    }

    private function voidNeverPaid(): Invoice
    {
        return Invoice::factory()->void()->create();
    }

    private function voidPaid(): Invoice
    {
        return Invoice::factory()->void()->create(['paid_at' => self::PAID_AT, 'payment_method' => PaymentMethod::BankTransfer]);
    }

    private function voidLegacyPaid(): Invoice
    {
        return Invoice::factory()->void()->create(['paid_at' => self::PAID_AT, 'payment_method' => null]);
    }

    /**
     * @return array<string, string>
     */
    private function details(Invoice $invoice): array
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        $heading = __('invoices.show.details');
        $list = (new DOMXPath($dom))->query("//section[h2[normalize-space()='{$heading}']]/dl")->item(0);

        $this->assertNotNull($list, 'The details list did not render.');

        $pairs = [];
        $term = null;

        foreach ($list->childNodes as $node) {
            if ($node->nodeName === 'dt') {
                $term = trim($node->textContent);
            }

            if ($node->nodeName === 'dd' && $term !== null) {
                $pairs[$term] = trim(preg_replace('/\s+/', ' ', $node->textContent));
                $term = null;
            }
        }

        return $pairs;
    }

    #[DataProvider('cases')]
    public function test_the_paid_rows_follow_whether_the_invoice_was_ever_paid(string $fixture, ?string $expectedMethod): void
    {
        $details = $this->details($this->{$fixture}());

        $paidOn = __('invoices.show.paid_on');
        $method = __('invoices.show.payment_method');

        if ($expectedMethod === null) {
            $this->assertArrayNotHasKey($paidOn, $details);
            $this->assertArrayNotHasKey($method, $details);

            return;
        }

        $this->assertSame(Carbon::parse(self::PAID_AT)->isoFormat('D MMM YYYY'), $details[$paidOn]);

        if ($expectedMethod === '') {
            $this->assertStringContainsString(__('app.value_none_spoken'), $details[$method], 'A legacy invoice renders the standard empty value.');
            $this->assertNotContains($details[$method], PaymentMethod::options());

            return;
        }

        $this->assertSame($expectedMethod, $details[$method]);
    }

    public function test_the_paid_rows_sit_after_due_and_before_the_void_rows(): void
    {
        $terms = array_keys($this->details($this->voidPaid()));

        $this->assertSame(
            [__('invoices.show.due'), __('invoices.show.paid_on'), __('invoices.show.payment_method'), __('invoices.void.voided')],
            array_slice($terms, array_search(__('invoices.show.due'), $terms, true), 4),
        );
    }
}
