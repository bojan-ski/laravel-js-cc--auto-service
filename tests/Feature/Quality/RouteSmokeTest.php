<?php

namespace Tests\Feature\Quality;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthorizationMatrix;
use Tests\Support\QaFixture;
use Tests\Support\WalksPages;
use Tests\TestCase;

/**
 * Every named route responds. GET routes are rendered here for every role the
 * matrix permits, against the realistic fixture; every other verb is driven
 * by the authorization matrix with a valid payload and an exact status. The
 * partition is asserted in both directions, so a route can neither be missed
 * nor named after it is deleted.
 *
 * This sweep runs WITHOUT the violation collectors on purpose: a 500 here
 * reports its real exception. LazyLoadingSweepTest installs them and lists
 * every violation instead.
 */
class RouteSmokeTest extends TestCase
{
    use LazilyRefreshDatabase;
    use WalksPages;

    public function test_every_named_route_is_walked_here_or_driven_by_the_matrix(): void
    {
        $getRoutes = [];
        $writeRoutes = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            in_array('GET', $route->methods(), true)
                ? $getRoutes[] = $name
                : $writeRoutes[] = $name;
        }

        $inventoryRoutes = array_values(array_unique(array_column(self::pageInventory(), 'route')));
        $matrixRoutes = [];

        foreach (self::matrix() as $row) {
            foreach ($row['requests'] as $request) {
                $matrixRoutes[] = $request['route'];
            }
        }

        $this->assertSame([], array_values(array_diff($getRoutes, $inventoryRoutes)), 'GET routes no sweep renders: '.implode(', ', array_diff($getRoutes, $inventoryRoutes)));
        $this->assertSame([], array_values(array_diff($inventoryRoutes, $getRoutes)), 'Inventory entries naming routes that are not GET routes: '.implode(', ', array_diff($inventoryRoutes, $getRoutes)));
        $this->assertSame([], array_values(array_diff($writeRoutes, $matrixRoutes)), 'Write routes the authorization matrix never drives: '.implode(', ', array_diff($writeRoutes, $matrixRoutes)));

        $this->assertGreaterThanOrEqual(35, count($getRoutes), 'The router reports far fewer GET routes than the application has.');
        $this->assertGreaterThanOrEqual(45, count($writeRoutes), 'The router reports far fewer write routes than the application has.');
    }

    /**
     * A GET route the matrix permits for a role must have at least one
     * inventory entry walked as that role — otherwise a page can silently
     * drop out of a role's sweep while the matrix still passes.
     */
    public function test_the_inventory_roles_agree_with_the_matrix(): void
    {
        $permitted = [];

        foreach (self::matrix() as $row) {
            if (! ($row['walkable'] ?? true)) {
                continue;
            }

            foreach ($row['requests'] as $request) {
                if ($request['method'] !== 'get') {
                    continue;
                }

                foreach (['guest', 'mechanic', 'receptionist', 'admin'] as $role) {
                    if ($row[$role] === self::matrixAllow()) {
                        $permitted[$request['route']][$role] = true;
                    }
                }
            }
        }

        $covered = [];

        foreach (self::pageInventory() as $entry) {
            foreach ($entry['roles'] as $role) {
                $covered[$entry['route']][$role] = true;
            }
        }

        $missing = [];

        foreach ($permitted as $route => $roles) {
            foreach (array_keys($roles) as $role) {
                if (! isset($covered[$route][$role])) {
                    $missing[] = "{$route} as {$role}";
                }
            }
        }

        $this->assertSame([], $missing, 'Permitted pages no sweep walks for that role: '.implode(', ', $missing));
        $this->assertGreaterThanOrEqual(25, count($permitted), 'The matrix yielded far fewer GET routes than it drives.');
    }

    #[DataProvider('roles')]
    public function test_every_page_responds_for_the_role(string $role, int $floor): void
    {
        $shop = QaFixture::build();

        $walked = $this->walk($shop, $this->actorFor($shop, $role), $role, function (string $label, array $entry, TestResponse $response): void {
            $this->assertPageRendered($response, $label);
            $this->assertNoBareKey($response, $label, $entry);
        });

        $this->assertGreaterThanOrEqual($floor, $walked, "The {$role} walk rendered only {$walked} pages; the inventory has shrunk.");
    }

    public function test_the_guest_pages_render(): void
    {
        $shop = QaFixture::build();

        $walked = $this->walk($shop, null, 'guest', function (string $label, array $entry, TestResponse $response): void {
            $this->assertPageRendered($response, $label);
            $this->assertNoBareKey($response, $label, $entry);
        });

        $this->assertSame(3, $walked, 'The guest inventory should hold exactly login, forgot-password and reset-password.');
    }

    /**
     * The detector must see a key in text and ignore the look-alikes it will
     * meet on real pages: attributes, scripts, filenames, email addresses,
     * URL paths and the shop's own domain.
     */
    public function test_the_bare_key_detector_is_live(): void
    {
        $html = <<<'HTML'
            <script>var key = "app.flash.success";</script>
            <style>.jobs.board { color: red }</style>
            <p title="jobs.board.title" data-key="parts.reasons.restock">jobs.statuses.received &amp; invoices.statuses.unpaid</p>
            <a href="/jobs/12">inspection.pdf</a> m.petrov@parts.example auto-service.test brake-pads.jpg
            HTML;

        $this->assertSame(['jobs.statuses.received', 'invoices.statuses.unpaid'], self::bareKeysIn($html));
        $this->assertSame([], self::bareKeysIn('<p>Nothing to see here: jobs. parts. filters.</p>'));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic', 50],
            'receptionist' => ['receptionist', 70],
            'admin' => ['admin', 90],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function matrix(): array
    {
        return self::matrixHolder()::matrix();
    }

    private static function matrixAllow(): string
    {
        return self::matrixHolder()::allow();
    }

    private static function matrixHolder(): object
    {
        return new class
        {
            use AuthorizationMatrix;

            public static function allow(): string
            {
                return self::ALLOW;
            }
        };
    }
}
