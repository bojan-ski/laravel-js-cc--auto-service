<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array EXPECTED = [
        'favicon.ico',
        'favicon.svg',
        'favicon-32x32.png',
        'favicon-16x16.png',
        'apple-touch-icon.png',
        'site.webmanifest',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'guest layout' => ['guest'],
            'app layout' => ['app'],
            'error page' => ['error'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_layout_links_the_full_icon_set(string $page): void
    {
        $response = match ($page) {
            'guest' => $this->get(route('login')),
            'app' => $this->actingAs(User::factory()->create())->get(route('dashboard')),
            'error' => $this->get('/no-such-page'),
        };

        $this->assertSame(self::EXPECTED, $this->linkedIcons($response));
    }

    public function test_every_linked_icon_exists_and_is_not_empty(): void
    {
        foreach (self::EXPECTED as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)), "public/{$file} is empty.");
        }
    }

    public function test_every_icon_the_manifest_names_exists(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    /**
     * @return list<string>
     */
    private function linkedIcons(TestResponse $response): array
    {
        preg_match_all('/<link rel="(?:icon|apple-touch-icon|manifest)" href="([^"]+)"/', $response->getContent(), $matches);

        return array_map(fn (string $href): string => ltrim((string) parse_url($href, PHP_URL_PATH), '/'), $matches[1]);
    }
}
