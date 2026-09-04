<?php

namespace Tests\Feature\Parts;

use App\Exceptions\UnreadableImport;
use App\Services\CsvReader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The reader owns the three encoding traps this feature measured before it was
 * planned: a UTF-8 BOM survives fgetcsv and breaks header matching, a
 * semicolon file read with a comma yields one column holding the whole line,
 * and European Excel writes semicolons by default.
 */
class CsvReaderTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->paths = [];

        parent::tearDown();
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csvreader');
        file_put_contents($path, $contents);
        $this->paths[] = $path;

        return $path;
    }

    private function read(string $contents): array
    {
        return app(CsvReader::class)->read($this->file($contents));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function delimiterProvider(): array
    {
        return [
            'comma' => ["sku,quantity\nBP-1,4\n"],
            'semicolon' => ["sku;quantity\nBP-1;4\n"],
            'tab' => ["sku\tquantity\nBP-1\t4\n"],
        ];
    }

    #[DataProvider('delimiterProvider')]
    public function test_it_detects_the_delimiter(string $contents): void
    {
        $result = $this->read($contents);

        $this->assertSame(['sku', 'quantity'], $result['headers']);
        $this->assertSame(['sku' => 'BP-1', 'quantity' => '4'], $result['rows'][0]['values']);
    }

    public function test_it_strips_a_utf8_bom_from_the_first_header(): void
    {
        $result = $this->read("\xEF\xBB\xBFsku,quantity\nBP-1,4\n");

        $this->assertSame('sku', $result['headers'][0]);
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $result['headers'][0]);
    }

    public function test_a_bom_file_still_maps_its_rows_by_header(): void
    {
        $result = $this->read("\xEF\xBB\xBFsku,quantity\nBP-1,4\n");

        $this->assertSame('BP-1', $result['rows'][0]['values']['sku']);
    }

    public function test_it_reads_crlf_line_endings(): void
    {
        $result = $this->read("sku,quantity\r\nBP-1,4\r\n");

        $this->assertSame(['sku', 'quantity'], $result['headers']);
        $this->assertSame('4', $result['rows'][0]['values']['quantity']);
    }

    public function test_a_quoted_field_may_contain_the_delimiter(): void
    {
        $result = $this->read("sku,name\nBP-1,\"Pads, front\"\n");

        $this->assertSame('Pads, front', $result['rows'][0]['values']['name']);
    }

    public function test_a_quoted_header_does_not_confuse_delimiter_detection(): void
    {
        $result = $this->read("\"sku;code\";quantity\nBP-1;4\n");

        $this->assertSame(['sku;code', 'quantity'], $result['headers']);
    }

    public function test_a_short_row_is_padded_rather_than_failing(): void
    {
        $result = $this->read("sku,quantity,name\nBP-1,4\n");

        $this->assertSame('', $result['rows'][0]['values']['name']);
    }

    public function test_a_long_row_drops_its_surplus(): void
    {
        $result = $this->read("sku,quantity\nBP-1,4,extra\n");

        $this->assertSame(['sku' => 'BP-1', 'quantity' => '4'], $result['rows'][0]['values']);
    }

    public function test_values_are_trimmed(): void
    {
        $result = $this->read("sku,quantity\n  BP-1 ,  4  \n");

        $this->assertSame(['sku' => 'BP-1', 'quantity' => '4'], $result['rows'][0]['values']);
    }

    public function test_blank_lines_are_skipped_but_do_not_shift_line_numbers(): void
    {
        $result = $this->read("sku,quantity\n\nBP-1,4\n\nBP-2,7\n");

        $this->assertCount(2, $result['rows']);
        $this->assertSame(3, $result['rows'][0]['line']);
        $this->assertSame(5, $result['rows'][1]['line']);
    }

    public function test_the_first_data_row_is_line_two(): void
    {
        $result = $this->read("sku,quantity\nBP-1,4\n");

        $this->assertSame(2, $result['rows'][0]['line']);
    }

    public function test_a_header_only_file_reads_as_no_rows(): void
    {
        $result = $this->read("sku,quantity\n");

        $this->assertSame(['sku', 'quantity'], $result['headers']);
        $this->assertSame([], $result['rows']);
    }

    public function test_an_empty_file_is_unreadable(): void
    {
        $this->expectException(UnreadableImport::class);

        $this->read('');
    }

    public function test_a_missing_file_is_unreadable(): void
    {
        $this->expectException(UnreadableImport::class);

        app(CsvReader::class)->read(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv');
    }

    public function test_a_line_written_by_the_reader_is_read_back_unchanged(): void
    {
        $reader = app(CsvReader::class);
        $line = $reader->toCsvLine(['sku', 'name with, comma', 'quoted "value"']);

        $result = $this->read($line."BP-1,x,y\n");

        $this->assertSame(['sku', 'name with, comma', 'quoted "value"'], $result['headers']);
    }
}
