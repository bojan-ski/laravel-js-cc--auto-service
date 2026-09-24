<?php

namespace Tests\Feature;

use App\Contracts\ProvidesStatusPresentation;
use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ComputesContrast;
use Tests\TestCase;

/**
 * `x-ui.status-badge` renders every status as `text-status-x` on `bg-status-x/10`,
 * so each token's legibility depends on it contrasting with a 10% tint of itself.
 * Three tokens failed that from spec 07 until spec 23 — `awaiting-parts` at
 * 2.67:1 — and five rounds of static auditing never saw it, because the failure
 * only exists once a browser blends the tint.
 *
 * This covers the light palette over the white panel; DarkPaletteTest covers
 * the dark one.
 */
class StatusBadgeContrastTest extends TestCase
{
    use ComputesContrast;

    private const AA_NORMAL_TEXT = 4.5;

    private const TINT_ALPHA = 0.10;

    private const WHITE = [255, 255, 255];

    /**
     * Every enum whose cases reach `x-ui.status-badge`. Driving the provider from
     * the enums rather than a hardcoded list means a status added later is
     * covered here the moment it exists.
     *
     * @return array<string, array{0: string}>
     */
    public static function badgeTokenProvider(): array
    {
        $cases = [
            ...JobStatus::cases(),
            ...AppointmentStatus::cases(),
            ...InvoiceStatus::cases(),
        ];

        $tokens = [];

        foreach ($cases as $case) {
            /** @var ProvidesStatusPresentation $case */
            $tokens[$case->colorToken()] = [$case->colorToken()];
        }

        return $tokens;
    }

    #[DataProvider('badgeTokenProvider')]
    public function test_every_badge_token_meets_aa_against_its_own_tint(string $token): void
    {
        $hex = $this->paletteHex($token);
        $this->assertNotSame('', $hex, "The palette has no --color-{$token}.");
        $color = $this->rgb($hex);
        $ratio = $this->contrast($color, $this->tint($color, self::WHITE, self::TINT_ALPHA));

        $this->assertGreaterThanOrEqual(
            self::AA_NORMAL_TEXT,
            $ratio,
            sprintf(
                'Badge token --color-%s (%s) contrasts %.2f:1 against a %d%% tint of itself, below the %.1f:1 AA bar for its 12px text. Darken the token; raising the tint opacity makes this worse, not better.',
                $token,
                $hex,
                $ratio,
                self::TINT_ALPHA * 100,
                self::AA_NORMAL_TEXT,
            ),
        );
    }

    /**
     * Guards the measurement itself. Without it, a `contrast()` that always
     * returned a large number would leave every assertion above green.
     */
    public function test_the_contrast_calculation_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(21.0, $this->contrast([0, 0, 0], [255, 255, 255]), 0.01);
        $this->assertEqualsWithDelta(1.0, $this->contrast([255, 255, 255], [255, 255, 255]), 0.01);

        // Measured in a browser during spec 23's review, before the token was darkened.
        $old = [0xCA, 0x8A, 0x04];
        $this->assertEqualsWithDelta(2.67, $this->contrast($old, $this->tint($old, self::WHITE, self::TINT_ALPHA)), 0.01);
    }

    public function test_the_palette_defines_every_token_the_badges_ask_for(): void
    {
        foreach (array_keys(self::badgeTokenProvider()) as $token) {
            $this->assertMatchesRegularExpression(
                '/^#[0-9A-Fa-f]{6}$/',
                $this->paletteHex($token),
                "The palette has no --color-{$token}, so its badge renders unstyled."
            );
        }
    }

    private function paletteHex(string $token): string
    {
        return $this->lightPalette()[$token] ?? '';
    }
}
