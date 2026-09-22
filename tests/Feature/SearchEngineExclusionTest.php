<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SearchEngineExclusionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ROBOTS_META = '<meta name="robots" content="noindex, nofollow">';

    public function test_robots_txt_disallows_every_path_for_every_crawler(): void
    {
        $lines = preg_split('/\R/', trim(file_get_contents(public_path('robots.txt'))));

        $this->assertSame(['User-agent: *', 'Disallow: /'], $lines);
    }

    public function test_the_guest_layout_tells_crawlers_not_to_index(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(self::ROBOTS_META, false);
    }

    public function test_the_app_layout_tells_crawlers_not_to_index(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(self::ROBOTS_META, false);
    }

    public function test_error_pages_tell_crawlers_not_to_index(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee(self::ROBOTS_META, false);
    }

    public function test_no_sitemap_is_served(): void
    {
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));

        $this->get('/sitemap.xml')->assertNotFound();
    }
}
