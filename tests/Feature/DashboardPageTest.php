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

    public function test_the_wordmark_links_back_to_the_dashboard(): void
    {
        $this->get('/')->assertSee('href="'.route('dashboard').'"', false);
    }

    /**
     * Spec 23 replaced the single page-level empty state with three panels, each
     * stating its own emptiness. `app.dashboard.empty` was deleted with it.
     */
    public function test_each_panel_states_its_own_emptiness(): void
    {
        $this->get('/')
            ->assertSee(__('dashboard.today.empty'))
            ->assertSee(__('dashboard.active_jobs.empty'))
            ->assertSee(__('dashboard.low_stock.empty'));
    }

    /**
     * Both `app.dashboard.*` keys are gone: `empty` because three panels replaced
     * the single page-level state, and `title` because it moved to the page's own
     * domain file, matching every other page in the application.
     */
    public function test_the_old_app_level_dashboard_keys_are_gone(): void
    {
        $this->assertSame('app.dashboard.empty', __('app.dashboard.empty'));
        $this->assertSame('app.dashboard.title', __('app.dashboard.title'));
        $this->assertSame('Dashboard', __('dashboard.title'));
    }

    public function test_page_renders_the_localized_document_title(): void
    {
        $this->get('/')->assertSee(__('app.document_title', [
            'page' => __('dashboard.title'),
            'app' => __('app.name'),
        ]), false);
    }
}
