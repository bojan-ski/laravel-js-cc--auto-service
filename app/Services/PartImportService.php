<?php

namespace App\Services;

use App\Enums\PartImportColumn;
use App\Enums\PartNumberType;
use App\Enums\StockMovementReason;
use App\Exceptions\PartImportFailed;
use App\Models\Brand;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PartImportService
{
    public function __construct(
        private CsvReader $reader,
        private PartService $parts,
        private StockService $stock,
        private PartNumberService $numbers,
        private SupplierService $suppliers,
        private BrandService $brands,
    ) {}

    /**
     * @return array{created: int, restocked: int, details_ignored: int}
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $parsed = $this->reader->read($file->getRealPath());

        $errors = $this->rejectBadHeaders($parsed['headers'], $actor);

        if ($errors !== []) {
            throw new PartImportFailed($errors);
        }

        $rows = $parsed['rows'];

        if ($rows === []) {
            throw new PartImportFailed([__('parts.import.errors.no_rows')]);
        }

        if (count($rows) > $this->maxRows()) {
            throw new PartImportFailed([__('parts.import.errors.too_many_rows', [
                'count' => count($rows),
                'max' => $this->maxRows(),
            ])]);
        }

        $existing = $this->existingPartsBySku($rows);
        $prepared = $this->validateRows($rows, $existing, $actor);

        return $this->write($prepared, $existing, $actor);
    }

    /**
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function rejectBadHeaders(array $headers, User $actor): array
    {
        $errors = [];
        $known = PartImportColumn::headers();

        foreach (PartImportColumn::alwaysRequired() as $column) {
            if (! in_array($column->value, $headers, true)) {
                $errors[] = __('parts.import.errors.missing_column', ['column' => $column->value]);
            }
        }

        foreach (array_diff($headers, $known) as $unknown) {
            $errors[] = __('parts.import.errors.unknown_column', ['column' => $unknown]);
        }

        if (count($headers) !== count(array_unique($headers))) {
            $errors[] = __('parts.import.errors.duplicate_column');
        }

        foreach (PartImportColumn::cases() as $column) {
            if ($column->isAdminOnly()
                && in_array($column->value, $headers, true)
                && Gate::forUser($actor)->denies('viewCostPrice', Part::class)) {
                $errors[] = __('parts.import.errors.cost_price_forbidden');
            }
        }

        return $errors;
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return array<string, Part>
     */
    private function existingPartsBySku(array $rows): array
    {
        $skus = array_values(array_unique(array_map(
            fn (array $row): string => $row['values'][PartImportColumn::Sku->value] ?? '',
            $rows,
        )));

        return Part::query()
            ->withTrashed()
            ->whereIn('sku', array_filter($skus))
            ->get()
            ->keyBy('sku')
            ->all();
    }

    /**
     * Validates every row before a single write happens, so a rejected file
     * never acquires a row lock. Brands are resolved once and reused.
     *
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @param  array<string, Part>  $existing
     * @return list<array{line: int, sku: string, quantity: int, values: array<string, string>, brand: ?Brand}>
     */
    private function validateRows(array $rows, array $existing, User $actor): array
    {
        $errors = [];
        $prepared = [];
        $seen = [];
        $brands = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $values = $row['values'];
            $sku = $values[PartImportColumn::Sku->value] ?? '';
            $rowErrors = [];

            if ($sku === '') {
                $errors[] = __('parts.import.errors.missing_sku', ['line' => $line]);

                continue;
            }

            if (isset($seen[$sku])) {
                $errors[] = __('parts.import.errors.duplicate_sku', [
                    'sku' => $sku,
                    'line' => $line,
                    'first' => $seen[$sku],
                ]);

                continue;
            }

            $seen[$sku] = $line;

            $part = $existing[$sku] ?? null;

            if ($part?->trashed() === true) {
                $rowErrors[] = __('parts.import.errors.deleted_part', ['line' => $line, 'sku' => $sku]);
            }

            $quantity = $this->parseQuantity($values[PartImportColumn::Quantity->value] ?? '');

            if ($quantity === null) {
                $rowErrors[] = __('parts.import.errors.bad_quantity', ['line' => $line, 'sku' => $sku]);
            }

            $brand = null;

            if ($part === null) {
                $rowErrors = [...$rowErrors, ...$this->newPartErrors($line, $sku, $values)];
            }

            $brandName = $values[PartImportColumn::Brand->value] ?? '';

            if ($part === null && $brandName !== '') {
                $brand = $brands[$brandName] ??= $this->brands->resolveByName($brandName);

                if ($brand === null) {
                    $rowErrors[] = __('parts.import.errors.unknown_brand', [
                        'line' => $line,
                        'brand' => $brandName,
                    ]);
                }
            }

            if ($rowErrors !== []) {
                $errors = [...$errors, ...$rowErrors];

                continue;
            }

            $prepared[] = [
                'line' => $line,
                'sku' => $sku,
                'quantity' => (int) $quantity,
                'values' => $values,
                'brand' => $brand,
            ];
        }

        if ($errors !== []) {
            throw new PartImportFailed($errors);
        }

        return $prepared;
    }

    /**
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function newPartErrors(int $line, string $sku, array $values): array
    {
        $errors = [];

        foreach (PartImportColumn::cases() as $column) {
            if (! $column->isRequiredForNewPart()) {
                continue;
            }

            if (($values[$column->value] ?? '') === '') {
                $errors[] = __('parts.import.errors.missing_for_new_part', [
                    'line' => $line,
                    'sku' => $sku,
                    'column' => $column->value,
                ]);
            }
        }

        $price = $values[PartImportColumn::UnitPrice->value] ?? '';

        if ($price !== '' && ! is_numeric($price)) {
            $errors[] = __('parts.import.errors.bad_price', ['line' => $line, 'sku' => $sku]);
        }

        $cost = $values[PartImportColumn::CostPrice->value] ?? '';

        if ($cost !== '' && ! is_numeric($cost)) {
            $errors[] = __('parts.import.errors.bad_cost', ['line' => $line, 'sku' => $sku]);
        }

        $reorder = $values[PartImportColumn::ReorderLevel->value] ?? '';

        if ($reorder !== '' && filter_var($reorder, FILTER_VALIDATE_INT) === false) {
            $errors[] = __('parts.import.errors.bad_reorder_level', ['line' => $line, 'sku' => $sku]);
        }

        return $errors;
    }

    private function parseQuantity(string $raw): ?int
    {
        $quantity = filter_var(trim($raw), FILTER_VALIDATE_INT);

        return $quantity === false || $quantity < 1 ? null : $quantity;
    }

    /**
     * @param  list<array{line: int, sku: string, quantity: int, values: array<string, string>, brand: ?Brand}>  $prepared
     * @param  array<string, Part>  $existing
     * @return array{created: int, restocked: int, details_ignored: int}
     */
    private function write(array $prepared, array $existing, User $actor): array
    {
        return DB::transaction(function () use ($prepared, $existing, $actor): array {
            $created = 0;
            $restocked = 0;
            $detailsIgnored = 0;
            $suppliers = [];

            foreach ($prepared as $row) {
                $part = $existing[$row['sku']] ?? null;

                if ($part === null) {
                    $part = $this->createPart($row);
                    $created++;
                } else {
                    $restocked++;

                    if ($this->carriesPartDetail($row['values'])) {
                        $detailsIgnored++;
                    }
                }

                $supplierName = $row['values'][PartImportColumn::Supplier->value] ?? '';
                $supplier = $supplierName === ''
                    ? null
                    : $suppliers[$supplierName] ??= $this->suppliers->resolveByName($supplierName);

                $this->stock->record(
                    $part,
                    $row['quantity'],
                    StockMovementReason::Restock,
                    $actor,
                    note: __('parts.import.movement_note'),
                    supplier: $supplier instanceof Supplier ? $supplier : null,
                );
            }

            return [
                'created' => $created,
                'restocked' => $restocked,
                'details_ignored' => $detailsIgnored,
            ];
        });
    }

    /**
     * @param  array{line: int, sku: string, quantity: int, values: array<string, string>, brand: ?Brand}  $row
     */
    private function createPart(array $row): Part
    {
        $values = $row['values'];

        $part = $this->parts->create([
            'sku' => $row['sku'],
            'name' => $values[PartImportColumn::Name->value],
            'brand_id' => $row['brand']?->getKey(),
            'category' => $this->optional($values, PartImportColumn::Category),
            'unit_price' => $values[PartImportColumn::UnitPrice->value],
            'cost_price' => $this->optional($values, PartImportColumn::CostPrice),
            'reorder_level' => (int) ($this->optional($values, PartImportColumn::ReorderLevel) ?? 0),
            'location' => $this->optional($values, PartImportColumn::Location),
        ]);

        foreach ($this->splitNumbers($values[PartImportColumn::OeNumbers->value] ?? '') as $number) {
            $this->numbers->add($part, PartNumberType::Oe, $number);
        }

        return $part;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function optional(array $values, PartImportColumn $column): ?string
    {
        $value = $values[$column->value] ?? '';

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function splitNumbers(string $raw): array
    {
        $numbers = array_map(trim(...), explode('|', $raw));

        $unique = [];

        foreach ($numbers as $number) {
            $normalized = IdentifierNormalizer::normalize($number);

            if ($normalized !== '' && ! isset($unique[$normalized])) {
                $unique[$normalized] = $number;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function carriesPartDetail(array $values): bool
    {
        foreach (PartImportColumn::cases() as $column) {
            if ($column->isPartDetail() && ($values[$column->value] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function maxRows(): int
    {
        return (int) config('imports.max_rows');
    }
}
