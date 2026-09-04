<?php

namespace Tests\Feature;

use App\Contracts\ProvidesStatusPresentation;
use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `x-ui.status-badge` renders every status as `text-status-x` on `bg-status-x/10`,
 * so each token's legibility depends on it contrasting with a 10% tint of itself.
 * Three tokens failed that from spec 07 until spec 23 — `awaiting-parts` at
 * 2.67:1 — and five rounds of static auditing never saw it, because the failure
 * only exists once a browser blends the tint.
 *
 * This reads the palette from `resources/css/app.css` rather than the built
 * stylesheet: `TestCase` stubs Vite and `public/build` is gitignored, so a test
 * touching build output would fail on a fresh clone.
 */
class StatusBadgeContrastTest extends TestCase
{
    private const AA_NORMAL_TEXT = 4.5;

    private const TINT_ALPHA = 0.10;

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
        $color = $this->paletteColor($token);
        $ratio = $this->contrast($color, $this->tint($color));

        $this->assertGreaterThanOrEqual(
            self::AA_NORMAL_TEXT,
            $ratio,
            sprintf(
                'Badge token --color-%s (%s) contrasts %.2f:1 against a %d%% tint of itself, below the %.1f:1 AA bar for its 12px text. Darken the token; raising the tint opacity makes this worse, not better.',
                $token,
                $this->paletteHex($token),
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
        $this->assertEqualsWithDelta(2.67, $this->contrast($old, $this->tint($old)), 0.01);
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
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/--color-'.preg_quote($token, '/').':\s*(#[0-9A-Fa-f]{6})\s*;/', (string) $css, $match);

        return $match[1] ?? '';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function paletteColor(string $token): array
    {
        $hex = ltrim($this->paletteHex($token), '#');

        $this->assertNotSame('', $hex, "The palette has no --color-{$token}.");

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * The browser composites the `/10` tint over the white panel in sRGB. Verified
     * against the values Playwright reported for all five colours in spec 23.
     *
     * @param  array{0: int, 1: int, 2: int}  $color
     * @return array{0: float, 1: float, 2: float}
     */
    private function tint(array $color): array
    {
        return array_map(
            fn (int $channel): float => self::TINT_ALPHA * $channel + (1 - self::TINT_ALPHA) * 255,
            $color,
        );
    }

    /**
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    private function contrast(array $a, array $b): float
    {
        $first = $this->relativeLuminance($a);
        $second = $this->relativeLuminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * @param  array<int, float|int>  $color
     */
    private function relativeLuminance(array $color): float
    {
        $linear = array_map(function (float|int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $color);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
