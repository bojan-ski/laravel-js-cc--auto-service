<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function routeAborting(int $status): string
    {
        $uri = '/error-probe-'.$status;

        Route::middleware('web')->get($uri, fn () => abort($status));

        return $uri;
    }

    public function test_the_forbidden_page_renders_for_a_guest(): void
    {
        $this->get($this->routeAborting(403))
            ->assertForbidden()
            ->assertSee(__('errors.forbidden.title'))
            ->assertSee(__('errors.back_to_login'));
    }

    public function test_the_forbidden_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->routeAborting(403))
            ->assertForbidden()
            ->assertSee(__('errors.forbidden.title'))
            ->assertSee(__('errors.back_to_dashboard'));
    }

    public function test_the_forbidden_page_does_not_disclose_the_required_role(): void
    {
        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get($this->routeAborting(403))
            ->assertForbidden();

        $response->assertDontSee(__('app.roles.admin'));
        $response->assertDontSee(__('app.roles.receptionist'));
        $response->assertDontSee(__('errors.not_found.message'));
    }

    public function test_the_not_found_page_renders_for_a_guest(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee(__('errors.not_found.title'))
            ->assertSee(__('errors.back_to_login'));
    }

    public function test_the_not_found_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee(__('errors.not_found.title'))
            ->assertSee(__('errors.back_to_dashboard'));
    }

    public function test_the_expired_page_renders_for_a_guest(): void
    {
        $this->get($this->routeAborting(419))
            ->assertStatus(419)
            ->assertSee(__('errors.expired.title'))
            ->assertSee(__('errors.back_to_login'));
    }

    public function test_the_expired_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->routeAborting(419))
            ->assertStatus(419)
            ->assertSee(__('errors.expired.title'))
            ->assertSee(__('errors.back_to_dashboard'));
    }

    public function test_the_server_error_page_renders_for_an_http_exception(): void
    {
        $this->get($this->routeAborting(500))
            ->assertStatus(500)
            ->assertSee(__('errors.server_error.title'));
    }

    public function test_the_server_error_page_renders_for_an_uncaught_exception_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/error-probe-throw', function () {
            throw new RuntimeException('Deliberate failure.');
        });

        $this->get('/error-probe-throw')
            ->assertStatus(500)
            ->assertSee(__('errors.server_error.title'))
            ->assertDontSee('Deliberate failure.');
    }

    public function test_the_heading_names_the_problem_and_not_only_the_status_code(): void
    {
        $content = $this->get($this->routeAborting(403))->assertForbidden()->getContent();

        $heading = preg_match('/<h1\b[^>]*>(.*?)<\/h1>/s', $content, $matches) ? $matches[1] : '';

        $this->assertStringContainsString('403', $heading);
        $this->assertStringContainsString(__('errors.forbidden.title'), $heading);
    }

    public function test_the_error_layout_exposes_a_main_landmark(): void
    {
        $this->get($this->routeAborting(403))
            ->assertForbidden()
            ->assertSee('<main', false);
    }

    public function test_the_maintenance_page_renders_while_the_application_is_down(): void
    {
        $this->artisan('down');

        try {
            $this->get('/')
                ->assertStatus(503)
                ->assertSee(__('errors.maintenance.title'));
        } finally {
            $this->artisan('up');
        }
    }
}
