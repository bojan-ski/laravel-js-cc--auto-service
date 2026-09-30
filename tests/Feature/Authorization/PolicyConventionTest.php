<?php

namespace Tests\Feature\Authorization;

use App\Models\Brand;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Support\AuthorizationMatrix;
use Tests\TestCase;

/**
 * The structural half of the matrix: the conventions authorization rests on,
 * asserted as conventions rather than through any one feature.
 *
 * A hidden control is never the protection, so every "this role does not see
 * the link" assertion here is paired with the route refusing that role anyway.
 */
class PolicyConventionTest extends TestCase
{
    use AuthorizationMatrix;
    use LazilyRefreshDatabase;

    /**
     * Spec 03 forbids a blanket admin bypass, because it would silently grant
     * every ability written after it.
     *
     * Deliberately domain-free: it defines its own ability and must keep working
     * when every model in this application has changed, because what it defends
     * is a convention rather than a feature.
     */
    public function test_an_admin_is_denied_an_ability_that_is_denied_to_everyone(): void
    {
        Gate::define('nobody-may', fn (User $user): bool => false);

        $this->assertFalse(Gate::forUser(User::factory()->admin()->create())->allows('nobody-may'));
        $this->assertFalse(Gate::forUser(User::factory()->receptionist()->create())->allows('nobody-may'));
        $this->assertFalse(Gate::forUser(User::factory()->mechanic()->create())->allows('nobody-may'));
    }

    public function test_no_auth_service_provider_exists_and_policies_resolve_by_discovery(): void
    {
        $this->assertFalse(
            File::exists(app_path('Providers/AuthServiceProvider.php')),
            'An AuthServiceProvider reintroduces a place to register a Gate::before().',
        );
        $this->assertFalse(class_exists('App\Providers\AuthServiceProvider'));

        // Discovery alone must be wiring the policies up.
        $this->assertInstanceOf(
            InvoicePolicy::class,
            Gate::getPolicyFor(Invoice::class),
        );
    }

    /**
     * The drift guard. This spec's own criterion — every route appears in the
     * matrix — has been violated twice by capabilities shipping without the
     * matrix following. Enforcing it makes a third violation fail the suite
     * instead of shipping.
     */
    public function test_every_registered_route_appears_in_the_matrix(): void
    {
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== null) {
                $registered[$route->getName()] = true;
            }
        }

        $matrixed = [];

        foreach (self::matrix() as $row) {
            foreach ($row['requests'] as $request) {
                $matrixed[$request['route']] = true;
            }
        }

        $uncovered = array_diff_key($registered, $matrixed);
        $stale = array_diff_key($matrixed, $registered);

        $this->assertSame(
            [],
            array_keys($uncovered),
            'Routes exist that no matrix row covers. Add the capability to the matrix rather than deleting this assertion: '
                .implode(', ', array_keys($uncovered)),
        );
        $this->assertSame(
            [],
            array_keys($stale),
            'The matrix names routes the application no longer registers: '.implode(', ', array_keys($stale)),
        );
    }

    /**
     * The matrix's own shape, pinned so it cannot shrink unnoticed.
     *
     * The spec asks for this count to be DERIVED from project-overview.md's
     * Authorization Summary rather than written down. It cannot be: `context/`
     * is gitignored (.gitignore:31), so the overview is not in the repository
     * and a committed test that parsed it would fail on any fresh clone. The
     * derivation happened when this matrix was written — the overview's
     * capabilities were counted by hand — and the literal below is the tripwire
     * that fails loudly if the matrix later shrinks.
     *
     * What this leaves uncovered, stated rather than papered over: a capability
     * added to the overview that introduces NO new route would be caught by
     * neither this count nor the route drift guard. Nothing in the repository
     * can see the overview, so no test can close that gap.
     */
    public function test_the_matrix_holds_every_capability_and_the_thirteen_admin_only_rows(): void
    {
        // One row per Authorization Summary capability, plus lettered rows
        // (4a, 4b, 10a, 10b, 16a, 20a) where a capability spans separate
        // routes carrying different verdicts.
        $this->assertCount(56, self::matrix());

        // Cast: PHP stores a numeric row key as an integer, so '13' comes back
        // as 13 while the lettered keys stay strings.
        $this->assertSame(
            ['13', '14', '27', '20a', '21', '28', '29', '37', '47', '25', '26', '33', '39'],
            array_map(strval(...), array_keys(self::adminOnlyMatrix())),
        );
    }

    public function test_a_mechanic_sees_no_invoices_nav_item_and_is_refused_the_route_anyway(): void
    {
        $link = 'href="'.route('invoices.index').'"';

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($link, false);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($link, false);

        // The hidden link is convenience; this is the protection.
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    public function test_a_non_admin_sees_no_staff_link_and_is_refused_the_section_anyway(): void
    {
        $link = 'href="'.route('admin.users.index').'"';

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($link, false);

        foreach (['mechanic', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee($link, false);

            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('admin.users.index'))
                ->assertForbidden();
        }
    }

    public function test_a_non_admin_sees_no_brands_link_and_is_refused_the_section_anyway(): void
    {
        $link = 'href="'.route('admin.brands.index').'"';

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($link, false);

        foreach (['mechanic', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee($link, false);

            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('admin.brands.index'))
                ->assertForbidden();
        }
    }

    public function test_there_is_no_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
        $this->assertFalse(Route::has('register'));
    }

    public function test_the_health_endpoint_answers_without_authentication(): void
    {
        $this->get('/up')->assertOk();
    }

    /**
     * The eight abilities no ROUTE can observe.
     *
     * `UserPolicy::viewAny|create|update` and every `BrandPolicy` ability sit
     * behind `role:admin`, which refuses a non-admin before the policy is ever
     * consulted — so no request to those routes can tell a correct policy from
     * a loosened one. That is why they are asserted as a PAIR: the middleware
     * is on the route, and the policy denies a receptionist on its own.
     *
     * Two corrections to the spec's wording, both measured here rather than
     * reasoned about:
     *
     *  - It is not true that loosening any of these "leaves the whole suite
     *    green". `UserPolicy::viewAny` and `BrandPolicy::viewAny` are also
     *    consulted by the nav, which renders on every authenticated page and is
     *    not behind `role:admin` — loosening either makes the Staff or Brands
     *    link appear for a mechanic and fails the two nav tests below.
     *  - The remaining six are unobservable through routes, but NOT
     *    unobservable: `test_the_policies_behind_the_admin_section_deny_a_
     *    receptionist_on_their_own` calls each ability directly, and
     *    `BrandPolicy::update => true` fails exactly that test and nothing else.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function abilitiesBehindTheAdminMiddleware(): array
    {
        return [
            'users.viewAny' => ['viewAny', 'admin.users.index'],
            'users.create' => ['create', 'admin.users.create'],
            'users.update' => ['update', 'admin.users.edit'],
            'brands.viewAny' => ['viewAny', 'admin.brands.index'],
            'brands.create' => ['create', 'admin.brands.create'],
            'brands.update' => ['update', 'admin.brands.edit'],
            'brands.delete' => ['delete', 'admin.brands.destroy'],
            'brands.restore' => ['restore', 'admin.brands.restore'],
        ];
    }

    public function test_every_admin_section_route_carries_the_role_middleware(): void
    {
        foreach (self::abilitiesBehindTheAdminMiddleware() as $case) {
            [, $routeName] = $case;

            $this->assertContains(
                'role:admin',
                Route::getRoutes()->getByName($routeName)->gatherMiddleware(),
                $routeName.' relies on its policy alone; the middleware band is what actually refuses a non-admin.',
            );
        }
    }

    public function test_the_policies_behind_the_admin_section_deny_a_receptionist_on_their_own(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $target = User::factory()->mechanic()->create();

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($receptionist)->allows($ability, User::class), "UserPolicy::{$ability}");
        }

        $this->assertFalse(Gate::forUser($receptionist)->allows('update', $target), 'UserPolicy::update');

        $brand = Brand::factory()->create();
        $trashedBrand = Brand::factory()->create();
        $trashedBrand->delete();

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($receptionist)->allows($ability, Brand::class), "BrandPolicy::{$ability}");
        }

        $this->assertFalse(Gate::forUser($receptionist)->allows('update', $brand), 'BrandPolicy::update');
        $this->assertFalse(Gate::forUser($receptionist)->allows('delete', $brand), 'BrandPolicy::delete');
        $this->assertFalse(Gate::forUser($receptionist)->allows('restore', $trashedBrand), 'BrandPolicy::restore');
    }

    /**
     * One asymmetry inside BrandPolicy worth pinning rather than describing.
     *
     * Only `admin.brands.restore` declares withTrashed(), so `delete`'s
     * `! $brand->trashed()` branch cannot be reached over HTTP — a trashed brand
     * 404s at the binding first. `restore`'s `$brand->trashed()` branch IS
     * reachable: an admin may legitimately ask to restore a live brand and be
     * refused. Only the second is a real refusal.
     */
    public function test_an_admin_is_refused_restoring_a_brand_that_was_never_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::factory()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('restore', $brand));

        $this->actingAs($admin)
            ->patch(route('admin.brands.restore', $brand))
            ->assertForbidden();
    }

    public function test_a_trashed_brand_is_unreachable_through_the_routes_that_do_not_declare_with_trashed(): void
    {
        $admin = User::factory()->admin()->create();
        $brand = Brand::factory()->create();
        $brand->delete();

        $this->actingAs($admin)->get(route('admin.brands.edit', $brand))->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.brands.destroy', $brand))->assertNotFound();
    }
}
