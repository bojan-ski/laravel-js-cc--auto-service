<?php

namespace App\Services;

use App\Exceptions\UnreadableImport;

class CsvReader
{
    private const BOM = "\xEF\xBB\xBF";

    /** @var list<string> */
    private const DELIMITERS = [',', ';', "\t"];

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: array<string, string>}>}
     */
    public function read(string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new UnreadableImport('the file could not be opened');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new UnreadableImport('the file is empty');
            }

            $firstLine = $this->stripBom($firstLine);
            $delimiter = $this->detectDelimiter($firstLine);

            $headers = str_getcsv(rtrim($firstLine, "\r\n"), $delimiter, '"', '\\');
            $headers = array_map(fn (?string $header): string => trim((string) $header), $headers);

            if ($headers === [] || $headers === ['']) {
                throw new UnreadableImport('the header row is empty');
            }

            return ['headers' => $headers, 'rows' => $this->readRows($handle, $headers, $delimiter)];
        } finally {
            fclose($handle);
        }
    }

    public function toCsvLine(array $values, string $delimiter = ','): string
    {
        $handle = fopen('php://memory', 'r+');

        try {
            fputcsv($handle, $values, $delimiter, '"', '\\', "\r\n");
            rewind($handle);

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function readRows($handle, array $headers, string $delimiter): array
    {
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            if ($this->isBlank($values)) {
                continue;
            }

            $rows[] = ['line' => $line, 'values' => $this->combine($headers, $values)];
        }

        return $rows;
    }

    /**
     * Pads a short row and drops a long one's surplus, so a ragged file is a
     * per-field validation problem rather than a fatal parse error.
     *
     * @param  list<string>  $headers
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $headers, array $values): array
    {
        $combined = [];

        foreach ($headers as $index => $header) {
            $combined[$header] = trim((string) ($values[$index] ?? ''));
        }

        return $combined;
    }

    /**
     * @param  list<string|null>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, self::BOM) ? substr($line, strlen(self::BOM)) : $line;
    }

    /**
     * Counts candidates outside quoted sections. A semicolon file read with a
     * comma yields one column holding the whole line, which surfaces as a
     * nonsense header rather than a parse failure — so this must not guess.
     */
    private function detectDelimiter(string $headerLine): string
    {
        $best = ',';
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($this->withoutQuotedSections($headerLine), $delimiter);

            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function withoutQuotedSections(string $line): string
    {
        return (string) preg_replace('/"[^"]*"/', '', $line);
    }
}
