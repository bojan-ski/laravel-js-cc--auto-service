<?php

namespace Tests\Feature;

use App\Http\Middleware\AddSecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function expectedHeaders(): array
    {
        return [
            'robots' => ['X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet'],
            'framing' => ['X-Frame-Options', 'DENY'],
            'sniffing' => ['X-Content-Type-Options', 'nosniff'],
            'referrer' => ['Referrer-Policy', 'same-origin'],
            'permissions' => ['Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()'],
        ];
    }

    private function assertCarriesEveryHeader(TestResponse $response): void
    {
        foreach (self::expectedHeaders() as [$name, $value]) {
            $response->assertHeader($name, $value);
        }
    }

    public function test_the_expected_header_set_matches_the_middleware(): void
    {
        $this->assertSame(
            array_combine(
                array_column(self::expectedHeaders(), 0),
                array_column(self::expectedHeaders(), 1),
            ),
            AddSecurityHeaders::HEADERS,
        );
    }

    #[DataProvider('expectedHeaders')]
    public function test_the_login_page_carries_the_header(string $name, string $value): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader($name, $value);
    }

    public function test_an_authenticated_page_carries_every_header(): void
    {
        $this->assertCarriesEveryHeader(
            $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk(),
        );
    }

    public function test_a_guest_redirect_carries_every_header(): void
    {
        $this->assertCarriesEveryHeader(
            $this->get(route('dashboard'))->assertRedirect(route('login')),
        );
    }

    public function test_a_rendered_error_page_carries_every_header(): void
    {
        $this->assertCarriesEveryHeader(
            $this->get('/no-such-page')->assertNotFound(),
        );
    }

    public function test_the_health_check_carries_every_header(): void
    {
        $this->assertCarriesEveryHeader(
            $this->get('/up')->assertOk(),
        );
    }

    public function test_a_file_download_carries_every_header(): void
    {
        $this->assertCarriesEveryHeader(
            $this->actingAs(User::factory()->receptionist()->create())
                ->get(route('parts.import.template'))
                ->assertOk(),
        );
    }

    public function test_a_secure_request_is_told_to_stay_on_https(): void
    {
        $this->get('https://localhost/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_a_plain_http_request_is_not_sent_hsts(): void
    {
        $this->get('http://localhost/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
