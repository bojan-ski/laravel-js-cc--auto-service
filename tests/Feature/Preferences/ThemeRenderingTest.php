<?php

namespace Tests\Feature\Preferences;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeRenderingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: Theme}>
     */
    public static function themeProvider(): array
    {
        return array_combine(
            array_map(fn (Theme $theme): string => $theme->value, Theme::cases()),
            array_map(fn (Theme $theme): array => [$theme], Theme::cases()),
        );
    }

    #[DataProvider('themeProvider')]
    public function test_the_app_layout_renders_the_users_theme(Theme $theme): void
    {
        $user = User::factory()->create();
        $user->theme = $theme;
        $user->save();

        $html = $this->actingAs($user)->get(route('jobs.index'))->assertOk()->getContent();

        $this->assertSame($theme->value, $this->documentTheme($html));
    }

    public function test_the_guest_layout_follows_the_operating_system(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertSame('system', $this->documentTheme($html));
    }

    /**
     * Error pages render through the guest layout, but a signed-in user who
     * hits one should keep their own theme rather than drop to the OS setting.
     */
    public function test_an_error_page_keeps_a_signed_in_users_theme(): void
    {
        $user = User::factory()->create();
        $user->theme = Theme::Dark;
        $user->save();

        $html = $this->actingAs($user)->get('/jobs/999999')->assertNotFound()->getContent();

        $this->assertSame('dark', $this->documentTheme($html));
    }

    public function test_an_error_page_for_a_guest_follows_the_operating_system(): void
    {
        $html = $this->get('/no-such-page')->assertNotFound()->getContent();

        $this->assertSame('system', $this->documentTheme($html));
    }

    private function documentTheme(string $html): ?string
    {
        preg_match('/<html\b[^>]*\bdata-theme="([^"]*)"/', $html, $match);

        return $match[1] ?? null;
    }
}
