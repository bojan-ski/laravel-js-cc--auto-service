<?php

namespace App\Enums;

use Illuminate\Support\Facades\App;

enum Locale: string
{
    case En = 'en';
    case Sr = 'sr';
    case Ro = 'ro';
    case De = 'de';

    public function label(): string
    {
        return __('app.languages.'.$this->value);
    }

    public function htmlLang(): string
    {
        return match ($this) {
            self::Sr => 'sr-Latn',
            default => $this->value,
        };
    }

    public static function shop(): self
    {
        return self::tryFrom((string) config('shop.locale')) ?? self::En;
    }

    /**
     * @return array{one: int, few: int, other: int}
     */
    public function pluralSamples(): array
    {
        return match ($this) {
            self::Sr => ['one' => 21, 'few' => 2, 'other' => 20],
            default => ['one' => 1, 'few' => 2, 'other' => 20],
        };
    }

    /**
     * @return array{one: string, few: string, other: string}
     */
    public static function pluralTemplates(string $key): array
    {
        return array_map(
            fn (int $sample): string => trans_choice($key, $sample, ['count' => ':count']),
            self::current()->pluralSamples(),
        );
    }

    public static function current(): self
    {
        return self::tryFrom(App::getLocale()) ?? self::shop();
    }

    /**
     * @return array<int, array{value: string, label: string, current: bool, lang: string}>
     */
    public static function options(): array
    {
        $current = self::current();

        return array_map(fn (self $locale): array => [
            'value' => $locale->value,
            'label' => $locale->label(),
            'current' => $locale === $current,
            'lang' => $locale->htmlLang(),
        ], self::cases());
    }
}
