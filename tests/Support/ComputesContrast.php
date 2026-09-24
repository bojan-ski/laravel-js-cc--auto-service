<?php

namespace Tests\Support;

/**
 * WCAG contrast arithmetic over the palette in `resources/css/app.css`.
 *
 * Reads the source stylesheet rather than the build: `TestCase` stubs Vite and
 * `public/build` is gitignored, so a test touching build output would fail on
 * a fresh clone.
 */
trait ComputesContrast
{
    /**
     * The `@theme` block — the light palette, and the only one Tailwind
     * generates utilities from.
     *
     * @return array<string, string>
     */
    protected function lightPalette(): array
    {
        return $this->paletteIn('/@theme\s*\{(.*?)\n\}/s');
    }

    /**
     * The `@variant dark` block that overrides the same tokens.
     *
     * @return array<string, string>
     */
    protected function darkPalette(): array
    {
        return $this->paletteIn('/@variant dark\s*\{(.*?)\n    \}/s');
    }

    /**
     * @return array<string, string>
     */
    private function paletteIn(string $blockPattern): array
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        preg_match($blockPattern, $css, $block);

        $this->assertNotEmpty($block, "No palette block matched {$blockPattern} in app.css.");

        preg_match_all('/--color-([a-z-]+):\s*(#[0-9A-Fa-f]{6})\s*;/', $block[1], $tokens);

        return array_combine($tokens[1], $tokens[2]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * A `/10`-style tint of a colour composited over an opaque background, in
     * sRGB — the way the browser blends it. Verified against the values
     * Playwright reported for all five light badge colours in spec 23.
     *
     * @param  array{0: int, 1: int, 2: int}  $color
     * @param  array{0: int, 1: int, 2: int}  $background
     * @return array{0: float, 1: float, 2: float}
     */
    protected function tint(array $color, array $background, float $alpha): array
    {
        return array_map(
            fn (int $channel, int $under): float => $alpha * $channel + (1 - $alpha) * $under,
            $color,
            $background,
        );
    }

    /**
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    protected function contrast(array $a, array $b): float
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
