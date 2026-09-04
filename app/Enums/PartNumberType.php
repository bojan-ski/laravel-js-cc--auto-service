<?php

namespace App\Enums;

enum PartNumberType: string
{
    case Oe = 'oe';
    case Catalog = 'catalog';
    case Ean = 'ean';
    case Supplier = 'supplier';

    public function label(): string
    {
        return __('parts.numbers.types.'.$this->value);
    }

    public function isCatalog(): bool
    {
        return $this === self::Catalog;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
