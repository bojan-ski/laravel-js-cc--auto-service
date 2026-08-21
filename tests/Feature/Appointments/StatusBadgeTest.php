<?php

namespace Tests\Feature\Appointments;

use App\Contracts\ProvidesStatusPresentation;
use App\Enums\AppointmentStatus;
use App\View\Components\Ui\StatusBadge;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use TypeError;

class StatusBadgeTest extends TestCase
{
    /**
     * The literal class strings Tailwind must be able to see. An interpolated
     * token would render an unstyled badge with no error.
     *
     * @return array<string, array{0: AppointmentStatus, 1: string, 2: string}>
     */
    public static function statusProvider(): array
    {
        return [
            'booked' => [AppointmentStatus::Booked, 'bg-status-booked/10', 'text-status-booked'],
            'arrived' => [AppointmentStatus::Arrived, 'bg-status-arrived/10', 'text-status-arrived'],
            'no-show' => [AppointmentStatus::NoShow, 'bg-status-no-show/10', 'text-status-no-show'],
            'cancelled' => [AppointmentStatus::Cancelled, 'bg-status-cancelled/10', 'text-status-cancelled'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_each_status_renders_its_own_literal_token_classes(AppointmentStatus $status, string $background, string $text): void
    {
        $rendered = $this->blade('<x-ui.status-badge :status="$status" />', ['status' => $status]);

        $rendered->assertSee($background, false);
        $rendered->assertSee($text, false);
        $rendered->assertSee($status->label());
    }

    #[DataProvider('statusProvider')]
    public function test_no_status_renders_in_the_brand_colour(AppointmentStatus $status): void
    {
        $this->blade('<x-ui.status-badge :status="$status" />', ['status' => $status])
            ->assertDontSee('brand');
    }

    public function test_the_badge_is_uppercase_and_tracked(): void
    {
        $this->blade('<x-ui.status-badge :status="$status" />', ['status' => AppointmentStatus::Booked])
            ->assertSee('uppercase', false)
            ->assertSee('tracking-wider', false);
    }

    public function test_the_component_refuses_a_status_that_does_not_implement_the_contract(): void
    {
        $this->expectException(TypeError::class);

        new StatusBadge('booked');
    }

    public function test_rendering_a_status_that_does_not_implement_the_contract_fails(): void
    {
        try {
            $this->blade('<x-ui.status-badge :status="$status" />', ['status' => 'booked']);
            $this->fail('Expected the badge to refuse a non-implementing status.');
        } catch (ViewException $exception) {
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
            $this->assertStringContainsString(ProvidesStatusPresentation::class, $exception->getMessage());
        }
    }
}
