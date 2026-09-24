<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ComputesContrast;
use Tests\TestCase;

/**
 * The dark palette in `resources/css/app.css` overrides every `@theme` colour
 * token under the `dark` variant. These assertions hold it to the same bar the
 * light palette meets: every pair the views actually render is checked, and a
 * pair that already falls short in light may not get worse in dark.
 */
class DarkPaletteTest extends TestCase
{
    use ComputesContrast;

    private const AA_NORMAL_TEXT = 4.5;

    private const TINT_ALPHA = 0.10;

    public function test_the_dark_block_overrides_every_light_token_and_nothing_else(): void
    {
        $light = array_keys($this->lightPalette());
        $dark = array_keys($this->darkPalette());

        $this->assertGreaterThanOrEqual(28, count($light), 'The light palette did not parse.');

        sort($light);
        sort($dark);

        $this->assertSame(
            $light,
            $dark,
            'A token missing from the dark block renders its light value in dark mode; an extra one styles nothing.',
        );
    }

    /**
     * Badges sit on cards (`surface`) and on hovered or muted rows
     * (`surface-muted`), so both backgrounds are checked.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function badgeOnBackgroundProvider(): array
    {
        $cases = [];

        foreach (array_keys(StatusBadgeContrastTest::badgeTokenProvider()) as $token) {
            foreach (['surface', 'surface-muted'] as $background) {
                $cases["{$token} on {$background}"] = [$token, $background];
            }
        }

        return $cases;
    }

    #[DataProvider('badgeOnBackgroundProvider')]
    public function test_every_badge_meets_aa_against_its_tint_in_dark(string $token, string $background): void
    {
        $palette = $this->darkPalette();
        $color = $this->rgb($palette[$token]);
        $under = $this->tint($color, $this->rgb($palette[$background]), self::TINT_ALPHA);
        $ratio = $this->contrast($color, $under);

        $this->assertGreaterThanOrEqual(
            self::AA_NORMAL_TEXT,
            $ratio,
            sprintf('Dark badge --color-%s (%s) on its tint over %s is %.2f:1.', $token, $palette[$token], $background, $ratio),
        );
    }

    /**
     * Text pairs that must meet AA in dark. Each is a foreground/background
     * combination some view renders; `text-inverse` sits on the nav bar, the
     * active tabs, and the primary and danger buttons at rest and on hover.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textPairProvider(): array
    {
        $pairs = [];

        foreach (['text', 'text-soft', 'text-muted', 'brand-dark'] as $foreground) {
            foreach (['background', 'surface', 'surface-muted'] as $background) {
                $pairs["{$foreground} on {$background}"] = [$foreground, $background];
            }
        }

        foreach (['surface-dark', 'brand-deep', 'danger', 'danger-dark'] as $background) {
            $pairs["text-inverse on {$background}"] = ['text-inverse', $background];
        }

        return $pairs;
    }

    #[DataProvider('textPairProvider')]
    public function test_text_pairs_meet_aa_in_dark(string $foreground, string $background): void
    {
        $palette = $this->darkPalette();
        $ratio = $this->contrast($this->rgb($palette[$foreground]), $this->rgb($palette[$background]));

        $this->assertGreaterThanOrEqual(
            self::AA_NORMAL_TEXT,
            $ratio,
            sprintf('Dark --color-%s on --color-%s is %.2f:1.', $foreground, $background, $ratio),
        );
    }

    /**
     * `brand-deep` exists so the primary button's hover can stay dark enough
     * for inverse text while `brand-dark` turns light for link hovers in dark
     * mode. It must hold in light too, where it replaced `brand-dark` on the
     * two buttons.
     */
    public function test_the_primary_button_hover_meets_aa_in_light(): void
    {
        $palette = $this->lightPalette();
        $ratio = $this->contrast($this->rgb($palette['text-inverse']), $this->rgb($palette['brand-deep']));

        $this->assertGreaterThanOrEqual(self::AA_NORMAL_TEXT, $ratio);
    }

    /**
     * Pairs that fall below AA in the LIGHT palette already. Fixing them is
     * out of this feature's scope; dark mode may not make them worse.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function inheritedShortfallProvider(): array
    {
        return [
            'primary button at rest' => ['text-inverse', 'brand'],
            'nav meta label' => ['text-muted', 'surface-dark'],
        ];
    }

    #[DataProvider('inheritedShortfallProvider')]
    public function test_dark_never_regresses_a_pair_the_light_palette_already_misses(string $foreground, string $background): void
    {
        $light = $this->lightPalette();
        $dark = $this->darkPalette();

        $lightRatio = $this->contrast($this->rgb($light[$foreground]), $this->rgb($light[$background]));
        $darkRatio = $this->contrast($this->rgb($dark[$foreground]), $this->rgb($dark[$background]));

        $this->assertGreaterThanOrEqual(
            $lightRatio - 0.001,
            $darkRatio,
            sprintf('--color-%s on --color-%s: light %.2f:1, dark %.2f:1.', $foreground, $background, $lightRatio, $darkRatio),
        );
    }
}
