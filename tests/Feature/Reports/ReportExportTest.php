<?php

namespace Tests\Feature\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    private const BOM = "\xEF\xBB\xBF";

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.vat_rate' => '20', 'shop.locale' => 'en']);
    }

    public function test_the_file_is_a_bom_prefixed_semicolon_csv_with_crlf_line_endings(): void
    {
        $this->invoiceAt('2026-09-05 10:00');

        $body = $this->export()->streamedContent();

        $this->assertStringStartsWith(self::BOM.'Number;"Issued on";Status;Job;', $body);
        $this->assertStringEndsWith("\r\n", $body);
        $this->assertSame(2, substr_count($body, "\r\n"));
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $body), 'Every line break outside a quoted value is CRLF.');
    }

    public function test_the_filename_names_the_period(): void
    {
        $response = $this->export();

        $this->assertStringContainsString(
            'attachment; filename=invoices-2026-09-01-2026-09-30.csv',
            $response->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_each_row_carries_every_column_with_a_decimal_comma_and_iso_dates(): void
    {
        $customer = Customer::factory()->company()->create(['name' => 'Fleet d.o.o.', 'address' => 'Bulevar 1']);
        $job = $this->completedJob($customer);
        $this->laborLine($job, User::factory()->mechanic()->create(), '12.00', '100.00', '10.00');
        $invoice = $this->issueAt($job, '2026-09-05 10:00');
        $this->payAt($invoice, '2026-09-07 08:00', PaymentMethod::BankTransfer);

        $rows = $this->rows($this->export()->streamedContent());

        $this->assertSame([
            $invoice->number,
            '2026-09-05',
            InvoiceStatus::Paid->label(),
            $job->reference,
            'Fleet d.o.o.',
            'Bulevar 1',
            $customer->tax_id,
            $customer->registration_number,
            '1200,00',
            '120,00',
            '1080,00',
            '20,00',
            '216,00',
            '1296,00',
            PaymentMethod::BankTransfer->label(),
            '2026-09-07',
            '',
        ], $rows[1]);
        $this->assertCount(17, $rows[0]);
    }

    public function test_void_rows_are_included_with_their_status_and_rows_run_in_id_order(): void
    {
        $first = $this->invoiceAt('2026-09-02 10:00');
        $voided = $this->voidAt($this->invoiceAt('2026-09-03 10:00', '1.00', '250.00'), '2026-09-04 09:00');
        $third = $this->invoiceAt('2026-09-05 10:00');

        $rows = array_slice($this->rows($this->export()->streamedContent()), 1);

        $this->assertSame([$first->number, $voided->number, $third->number], array_column($rows, 0));
        $this->assertSame(InvoiceStatus::Void->label(), $rows[1][2]);
        $this->assertSame('300,00', $rows[1][13]);
        $this->assertSame('2026-09-04', $rows[1][16]);
    }

    public function test_an_empty_period_downloads_the_header_row_only(): void
    {
        $body = $this->export()->streamedContent();

        $this->assertStringStartsWith(self::BOM, $body);
        $this->assertSame(1, substr_count($body, "\r\n"));
        $this->assertCount(1, $this->rows($body));
    }

    public function test_the_file_is_byte_identical_for_a_receptionist_and_an_admin(): void
    {
        $this->invoiceAt('2026-09-05 10:00');
        $this->voidAt($this->invoiceAt('2026-09-06 10:00'), '2026-09-07 10:00');

        $this->assertSame(
            $this->export('receptionist')->streamedContent(),
            $this->export('admin')->streamedContent(),
        );
    }

    /**
     * The file leaves the shop, like customer email, so it is written in the
     * shop language whoever downloads it. The downloader here is on German.
     * Wrapping withLocale() around the generator's creation, or around a body
     * that yields inside the closure, restores German before the first line is
     * built — so both wrong placements fail this test.
     */
    public function test_headers_and_labels_are_in_the_shop_language_not_the_downloaders(): void
    {
        config(['shop.locale' => 'sr']);
        $this->payAt($this->invoiceAt('2026-09-05 10:00'), '2026-09-05 11:00', PaymentMethod::Cash);

        $rows = $this->rows($this->export('receptionist', Locale::De)->streamedContent());

        $this->assertSame(__('reports.csv.number', [], 'sr'), $rows[0][0]);
        $this->assertSame(__('reports.csv.voided_on', [], 'sr'), $rows[0][16]);
        $this->assertSame(__('invoices.statuses.paid', [], 'sr'), $rows[1][2]);
        $this->assertSame(__('invoices.payment_methods.cash', [], 'sr'), $rows[1][14]);
        $this->assertNotSame(__('reports.csv.number', [], 'sr'), __('reports.csv.number', [], 'de'));
        $this->assertNotSame(__('invoices.statuses.paid', [], 'sr'), __('invoices.statuses.paid', [], 'de'));
    }

    public function test_the_page_after_a_download_is_still_in_the_downloaders_language(): void
    {
        config(['shop.locale' => 'sr']);
        $user = User::factory()->receptionist()->create(['locale' => Locale::De]);

        $this->actingAs($user)->get(route('reports.export'))->streamedContent();

        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee(__('reports.title', [], 'de'));
    }

    /**
     * Buyer details are free text typed at the counter, and Excel executes a
     * cell that begins with `=`. Tested with a real buyer name, as the spec
     * asks, not a synthetic string.
     */
    public function test_a_buyer_name_that_looks_like_a_formula_is_neutralized(): void
    {
        $customer = Customer::factory()->create([
            'name' => '=HYPERLINK("http://evil.test","Pay here")',
            'address' => '@SUM(1+1)',
        ]);
        $job = $this->completedJob($customer);
        $this->laborLine($job, User::factory()->mechanic()->create(), '1.00', '100.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $row = $this->rows($this->export()->streamedContent())[1];

        $this->assertSame("'=HYPERLINK(\"http://evil.test\",\"Pay here\")", $row[4]);
        $this->assertSame("'@SUM(1+1)", $row[5]);
        $this->assertSame('120,00', $row[13]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function formulaPrefixProvider(): array
    {
        return ['equals' => ['='], 'plus' => ['+'], 'minus' => ['-'], 'at' => ['@'], 'tab' => ["\t"], 'carriage return' => ["\r"]];
    }

    #[DataProvider('formulaPrefixProvider')]
    public function test_every_formula_prefix_is_neutralized(string $prefix): void
    {
        $job = $this->completedJob(Customer::factory()->create(['name' => $prefix.'Petrović']));
        $this->laborLine($job, User::factory()->mechanic()->create(), '1.00', '100.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $this->assertSame("'".$prefix.'Petrović', $this->rows($this->export()->streamedContent())[1][4]);
    }

    public function test_an_address_with_a_line_break_reads_back_as_one_cell(): void
    {
        $job = $this->completedJob(Customer::factory()->create(['address' => "Bulevar oslobođenja 4\n21000 Novi Sad"]));
        $this->laborLine($job, User::factory()->mechanic()->create(), '1.00', '100.00');
        $this->issueAt($job, '2026-09-05 10:00');

        $rows = $this->rows($this->export()->streamedContent());

        $this->assertCount(2, $rows);
        $this->assertSame("Bulevar oslobođenja 4\n21000 Novi Sad", $rows[1][5]);
        $this->assertCount(17, $rows[1]);
    }

    /**
     * lazyById() reads the last id from the row only after a FULL chunk, so a
     * dropped `'id'` alias, or broken paging, shows only once the export spans
     * more than one chunk. Five invoices in chunks of two: two full chunks,
     * then a partial one.
     */
    public function test_an_export_spanning_several_chunks_keeps_every_row_in_order(): void
    {
        $numbers = [];

        foreach (['2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05', '2026-09-06'] as $day) {
            $numbers[] = $this->invoiceAt("{$day} 10:00")->number;
        }

        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));

        $body = implode('', iterator_to_array(app(ReportService::class)->exportLines(
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30'),
            2,
        ), false));

        $this->assertSame($numbers, array_column(array_slice($this->rows($body), 1), 0));
    }

    private function export(string $role = 'receptionist', Locale $locale = Locale::En): TestResponse
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00'));

        return $this->actingAs(User::factory()->{$role}()->create(['locale' => $locale]))
            ->get(route('reports.export', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk();
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $body): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($body, strlen(self::BOM)));
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
