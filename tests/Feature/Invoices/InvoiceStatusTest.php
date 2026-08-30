<?php

namespace Tests\Feature\Invoices;

use App\Contracts\ProvidesStatusPresentation;
use App\Enums\InvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function test_the_enum_implements_the_presentation_contract(): void
    {
        $this->assertInstanceOf(ProvidesStatusPresentation::class, InvoiceStatus::Unpaid);
    }

    public function test_the_cases_are_exactly_the_three_lifecycle_states(): void
    {
        $this->assertSame(
            ['unpaid', 'paid', 'void'],
            array_map(fn (InvoiceStatus $status): string => $status->value, InvoiceStatus::cases()),
        );
    }

    /**
     * @return array<string, array{0: InvoiceStatus, 1: string, 2: string}>
     */
    public static function statusProvider(): array
    {
        return [
            'unpaid' => [InvoiceStatus::Unpaid, 'status-unpaid', 'Unpaid'],
            'paid' => [InvoiceStatus::Paid, 'status-paid', 'Paid'],
            'void' => [InvoiceStatus::Void, 'status-void', 'Void'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_each_status_maps_to_its_own_token_and_label(InvoiceStatus $status, string $token, string $label): void
    {
        $this->assertSame($token, $status->colorToken());
        $this->assertSame($label, $status->label());
    }

    /**
     * The three invoice tokens were orphaned until this spec: without them the
     * badge falls back to a grey default, and no test of rendered HTML can see
     * the difference, because the class attribute is present either way. This
     * asserts the mapping the component actually applies.
     *
     * @return array<string, array{0: InvoiceStatus, 1: string, 2: string}>
     */
    public static function badgeProvider(): array
    {
        return [
            'unpaid' => [InvoiceStatus::Unpaid, 'bg-status-unpaid/10', 'text-status-unpaid'],
            'paid' => [InvoiceStatus::Paid, 'bg-status-paid/10', 'text-status-paid'],
            'void' => [InvoiceStatus::Void, 'bg-status-void/10', 'text-status-void'],
        ];
    }

    #[DataProvider('badgeProvider')]
    public function test_the_badge_renders_the_literal_token_classes(InvoiceStatus $status, string $background, string $text): void
    {
        $rendered = $this->blade('<x-ui.status-badge :status="$status" />', ['status' => $status]);

        $rendered->assertSee($background, false);
        $rendered->assertSee($text, false);
        $rendered->assertSee($status->label());
    }

    #[DataProvider('badgeProvider')]
    public function test_no_invoice_status_falls_back_to_the_unstyled_default(InvoiceStatus $status): void
    {
        $this->blade('<x-ui.status-badge :status="$status" />', ['status' => $status])
            ->assertDontSee('bg-surface-muted text-text-soft', false);
    }

    public function test_only_the_void_case_reports_itself_as_void(): void
    {
        $this->assertTrue(InvoiceStatus::Void->isVoid());
        $this->assertFalse(InvoiceStatus::Unpaid->isVoid());
        $this->assertFalse(InvoiceStatus::Paid->isVoid());
    }
}
