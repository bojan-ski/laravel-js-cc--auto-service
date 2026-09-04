<?php

namespace App\Enums;

enum QualityTier: string
{
    case Aftermarket = 'aftermarket';
    case Original = 'original';
    case FirstFit = 'first_fit';
    case Genuine = 'genuine';

    public function label(): string
    {
        return __('brands.tiers.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $tier) {
            $options[$tier->value] = $tier->label();
        }

        return $options;
    }
}
