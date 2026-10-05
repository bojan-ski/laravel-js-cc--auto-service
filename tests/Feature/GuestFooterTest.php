<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestFooterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pagesAndLocales(): array
    {
        $copyright = [
            'en' => '© 2031 Auto Service | All rights reserved | code BPdevelopment',
            'sr' => '© 2031 Auto Servis | Sva prava zadržana | code BPdevelopment',
            'ro' => '© 2031 Auto Service | Toate drepturile rezervate | code BPdevelopment',
            'de' => '© 2031 Auto Service | Alle Rechte vorbehalten | code BPdevelopment',
        ];

        $cases = [];

        foreach (['login', 'forgot', 'reset', 'error'] as $page) {
            foreach ($copyright as $locale => $text) {
                $cases["{$page} in {$locale}"] = [$page, $locale, $text];
            }
        }

        return $cases;
    }

    /**
     * The year is read from the clock, so a frozen 2031 proves it is not a literal.
     */
    #[DataProvider('pagesAndLocales')]
    public function test_every_guest_shell_page_carries_the_footer_after_main(string $page, string $locale, string $text): void
    {
        $this->travelTo(now()->setDate(2031, 3, 15));

        $url = match ($page) {
            'login' => route('login'),
            'forgot' => route('password.request'),
            'reset' => route('password.reset', ['token' => 'any-token']),
            'error' => '/no-such-page',
        };

        $html = $this->withSession([SetLocale::SESSION_KEY => $locale])->get($url)->getContent();

        $this->assertMatchesRegularExpression(
            '#</main>\s*<footer[^>]*>\s*'.preg_quote(e($text), '#').'\s*</footer>#u',
            $html,
        );
    }

    public function test_the_footer_shows_the_copyright_sign_not_an_entity_name(): void
    {
        $html = $this->get(route('login'))->getContent();

        $this->assertStringContainsString('© '.now()->year.' Auto Service', $html);
        $this->assertStringNotContainsString('&amp;copy;', $html);
    }
}
