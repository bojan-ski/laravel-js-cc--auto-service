<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_route_returns_ok(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_dashboard_route_is_named(): void
    {
        $this->assertSame(url('/'), route('dashboard'));
    }

    public function test_page_renders_the_localized_application_name(): void
    {
        $this->get('/')->assertSee(__('app.name'));
    }

    public function test_page_renders_the_dashboard_navigation_label(): void
    {
        $this->get('/')->assertSee(__('app.nav.dashboard'));
    }

    public function test_page_renders_the_empty_state(): void
    {
        $this->get('/')->assertSee(__('app.dashboard.empty'));
    }

    public function test_page_renders_the_localized_document_title(): void
    {
        $this->get('/')->assertSee(__('app.document_title', [
            'page' => __('app.dashboard.title'),
            'app' => __('app.name'),
        ]), false);
    }
}
