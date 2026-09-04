<?php

namespace App\Enums;

enum PartImportColumn: string
{
    case Sku = 'sku';
    case Quantity = 'quantity';
    case Name = 'name';
    case UnitPrice = 'unit_price';
    case CostPrice = 'cost_price';
    case Brand = 'brand';
    case Category = 'category';
    case Location = 'location';
    case ReorderLevel = 'reorder_level';
    case Supplier = 'supplier';
    case OeNumbers = 'oe_numbers';

    public function isAlwaysRequired(): bool
    {
        return match ($this) {
            self::Sku, self::Quantity => true,
            default => false,
        };
    }

    public function isRequiredForNewPart(): bool
    {
        return match ($this) {
            self::Name, self::UnitPrice => true,
            default => false,
        };
    }

    public function isAdminOnly(): bool
    {
        return $this === self::CostPrice;
    }

    /**
     * A column describing the part record itself. These are read when a row
     * creates a part and ignored when it restocks one, because an import never
     * edits an existing part. Quantity, supplier and the SKU itself are not
     * details: they describe the delivery, not the part.
     */
    public function isPartDetail(): bool
    {
        return match ($this) {
            self::Sku, self::Quantity, self::Supplier => false,
            default => true,
        };
    }

    public function description(): string
    {
        return __('parts.import.columns.'.$this->value);
    }

    public function exampleValue(): string
    {
        return match ($this) {
            self::Sku => 'BP-1042',
            self::Quantity => '8',
            self::Name => 'Brake pads front',
            self::UnitPrice => '4200.00',
            self::CostPrice => '2600.00',
            self::Brand => 'Bosch',
            self::Category => 'Braking',
            self::Location => 'A3',
            self::ReorderLevel => '4',
            self::Supplier => 'Febi Bilstein',
            self::OeNumbers => '1K0615301AA|8K0698451D',
        };
    }

    /**
     * The columns a given actor may actually submit. The template and the
     * on-page column list are both built from this, so a receptionist is never
     * handed a template carrying a column their own import would reject.
     *
     * @return list<self>
     */
    public static function submittableWith(bool $withAdminOnly): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $column): bool => $withAdminOnly || ! $column->isAdminOnly(),
        ));
    }

    /**
     * @param  list<self>|null  $columns
     * @return list<string>
     */
    public static function headers(?array $columns = null): array
    {
        return array_map(fn (self $column): string => $column->value, $columns ?? self::cases());
    }

    /**
     * @return list<self>
     */
    public static function alwaysRequired(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $column): bool => $column->isAlwaysRequired(),
        ));
    }
}
