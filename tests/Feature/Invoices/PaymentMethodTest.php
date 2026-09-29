<?php

namespace Tests\Feature\Invoices;

use App\Enums\Locale;
use App\Enums\PaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    /**
     * @return array<string, array{Locale}>
     */
    public static function locales(): array
    {
        $locales = [];

        foreach (Locale::cases() as $locale) {
            $locales[$locale->value] = [$locale];
        }

        return $locales;
    }

    public function test_there_are_exactly_three_methods_in_order(): void
    {
        $this->assertSame(
            ['cash', 'card', 'bank_transfer'],
            array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()),
        );
    }

    public function test_the_english_labels(): void
    {
        $this->assertSame('Cash', PaymentMethod::Cash->label());
        $this->assertSame('Card', PaymentMethod::Card->label());
        $this->assertSame('Bank transfer', PaymentMethod::BankTransfer->label());
    }

    #[DataProvider('locales')]
    public function test_every_label_resolves_in_every_locale(Locale $locale): void
    {
        app()->setLocale($locale->value);

        foreach (PaymentMethod::cases() as $method) {
            $label = $method->label();

            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('invoices.payment_methods', $label, "{$locale->value}: {$method->value} has no translation.");
        }
    }

    public function test_the_options_map_each_value_to_its_label_in_case_order(): void
    {
        $this->assertSame([
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank transfer',
        ], PaymentMethod::options());
    }
}
