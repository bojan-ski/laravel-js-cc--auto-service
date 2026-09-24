<?php

namespace Tests\Feature\Preferences;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: Theme}>
     */
    public static function roleAndThemeProvider(): array
    {
        $cases = [];

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            foreach (Theme::cases() as $theme) {
                $cases["{$role} chooses {$theme->value}"] = [$role, $theme];
            }
        }

        return $cases;
    }

    #[DataProvider('roleAndThemeProvider')]
    public function test_every_role_can_save_every_theme(string $role, Theme $theme): void
    {
        $start = $theme === Theme::Light ? Theme::Dark : Theme::Light;
        $user = User::factory()->{$role}()->create();
        $user->theme = $start;
        $user->save();

        $this->actingAs($user)
            ->from(route('jobs.index'))
            ->patch(route('preferences.theme.update'), ['theme' => $theme->value])
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($theme, $user->fresh()->theme);
    }

    public function test_it_changes_only_the_signed_in_users_theme(): void
    {
        $user = User::factory()->create();
        $colleague = User::factory()->create();

        $this->actingAs($user)->patch(route('preferences.theme.update'), ['theme' => 'dark']);

        $this->assertSame(Theme::Dark, $user->fresh()->theme);
        $this->assertSame(Theme::System, $colleague->fresh()->theme);
    }

    public function test_without_a_previous_page_it_returns_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('preferences.theme.update'), ['theme' => 'dark'])
            ->assertRedirect(route('dashboard'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['theme' => '']],
            'unknown value' => [['theme' => 'midnight']],
            'case differs' => [['theme' => 'Dark']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_an_invalid_theme_is_refused_and_the_saved_one_kept(array $payload): void
    {
        $user = User::factory()->create();
        $user->theme = Theme::Light;
        $user->save();

        $this->actingAs($user)
            ->from(route('jobs.index'))
            ->patch(route('preferences.theme.update'), $payload)
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasErrors('theme');

        $this->assertSame(Theme::Light, $user->fresh()->theme);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->patch(route('preferences.theme.update'), ['theme' => 'dark'])
            ->assertRedirect(route('login'));
    }
}
