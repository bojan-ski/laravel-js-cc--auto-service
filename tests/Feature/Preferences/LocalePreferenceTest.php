<?php

namespace Tests\Feature\Preferences;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: Locale}>
     */
    public static function roleAndLocaleProvider(): array
    {
        $cases = [];

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            foreach (Locale::cases() as $locale) {
                $cases["{$role} chooses {$locale->value}"] = [$role, $locale];
            }
        }

        return $cases;
    }

    #[DataProvider('roleAndLocaleProvider')]
    public function test_every_role_can_save_every_language(string $role, Locale $locale): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->from(route('jobs.index'))
            ->patch(route('preferences.locale.update'), ['locale' => $locale->value])
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($locale, $user->fresh()->locale);
    }

    public function test_a_new_user_has_not_chosen_a_language(): void
    {
        $this->assertNull(User::factory()->create()->fresh()->locale);
    }

    public function test_the_language_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['locale' => 'de']);

        $this->assertNull($user->locale);
    }

    public function test_it_changes_only_the_signed_in_users_language(): void
    {
        $user = User::factory()->create();
        $colleague = User::factory()->create();

        $this->actingAs($user)->patch(route('preferences.locale.update'), ['locale' => 'ro']);

        $this->assertSame(Locale::Ro, $user->fresh()->locale);
        $this->assertNull($colleague->fresh()->locale);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['locale' => '']],
            'unsupported language' => [['locale' => 'fr']],
            'the script-tagged code, which Laravel has no plural rules for' => [['locale' => 'sr_Latn']],
            'case differs' => [['locale' => 'DE']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_an_invalid_language_is_refused_and_the_saved_one_kept(array $payload): void
    {
        $user = User::factory()->create();
        $user->locale = Locale::De;
        $user->save();

        $this->actingAs($user)
            ->from(route('jobs.index'))
            ->patch(route('preferences.locale.update'), $payload)
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasErrors('locale');

        $this->assertSame(Locale::De, $user->fresh()->locale);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->patch(route('preferences.locale.update'), ['locale' => 'de'])
            ->assertRedirect(route('login'));
    }
}
