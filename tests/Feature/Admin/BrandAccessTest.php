<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function deniedRoleProvider(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist']];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function routes(Brand $brand): array
    {
        return [
            ['get', route('admin.brands.index')],
            ['get', route('admin.brands.create')],
            ['post', route('admin.brands.store')],
            ['get', route('admin.brands.edit', $brand)],
            ['put', route('admin.brands.update', $brand)],
            ['delete', route('admin.brands.destroy', $brand)],
            ['patch', route('admin.brands.restore', $brand)],
        ];
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_a_non_admin_is_refused_every_brand_route(string $role): void
    {
        $brand = Brand::factory()->create();
        $user = User::factory()->{$role}()->create();

        foreach ($this->routes($brand) as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_from_every_brand_route(): void
    {
        $brand = Brand::factory()->create();

        foreach ($this->routes($brand) as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    public function test_an_admin_reaches_the_index(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.brands.index'))
            ->assertOk();
    }

    public function test_an_admin_sees_the_brands_link_in_both_user_menus(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, __('app.nav.brands')));
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_a_non_admin_sees_no_brands_link(string $role): void
    {
        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($html, __('app.nav.brands')));
    }

    /**
     * @return list<string>
     */
    private function headerBrandLinks(string $html): array
    {
        preg_match('/<header\b.*?<\/header>/s', $html, $header);

        $this->assertNotEmpty($header, 'No header rendered.');

        preg_match_all(
            '/<a\b[^>]*href="'.preg_quote(route('admin.brands.index'), '/').'"[^>]*>/s',
            $header[0],
            $links,
        );

        return $links[0];
    }

    public function test_the_brands_link_is_marked_current_inside_the_section(): void
    {
        $admin = User::factory()->admin()->create();

        $inSection = $this->headerBrandLinks(
            $this->actingAs($admin)->get(route('admin.brands.index'))->assertOk()->getContent()
        );

        $this->assertCount(2, $inSection, 'Expected a desktop and a mobile Brands link.');

        foreach ($inSection as $link) {
            $this->assertStringContainsString('aria-current="page"', $link);
        }

        $elsewhere = $this->headerBrandLinks(
            $this->actingAs($admin)->get('/')->assertOk()->getContent()
        );

        $this->assertCount(2, $elsewhere);

        foreach ($elsewhere as $link) {
            $this->assertStringNotContainsString('aria-current="page"', $link);
        }
    }

    public function test_the_staff_link_still_renders_beside_it(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, __('app.nav.staff')));
    }
}
