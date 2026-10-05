<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Row 2 of the authorization matrix: the application has no public surface, so
 * every authenticated route must send a guest to the login page.
 *
 * The route list is enumerated from the router, never hand-written. A
 * hand-written list silently stops covering the route someone adds next month,
 * which is the one most likely to be unguarded.
 */
class GuestAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The health endpoint is the application's one deliberate unauthenticated,
     * non-guest route.
     */
    private const HEALTH_URI = 'up';

    /**
     * The refusal is a redirect to login and nothing else — never a bare 401, a
     * 403, or a partial view — so signing in returns the guest to what they
     * asked for. isRedirect(url) asserts both halves, which is why there is no
     * separate "not a status code" sweep.
     */
    public function test_a_guest_is_redirected_to_login_from_every_authenticated_route(): void
    {
        $swept = 0;

        foreach ($this->authenticatedRoutes() as $route) {
            $method = $this->verbFor($route);
            $uri = $this->urlFor($route);

            $response = $this->{$method}($uri);

            $this->assertTrue(
                $response->isRedirect(route('login')),
                sprintf(
                    '%s %s (%s) must redirect a guest to login, got %d%s.',
                    strtoupper($method),
                    $uri,
                    $route->getName() ?? 'unnamed',
                    $response->getStatusCode(),
                    $response->isRedirect() ? ' → '.$response->headers->get('Location') : '',
                ),
            );

            $swept++;
        }

        $this->assertGreaterThan(70, $swept, 'The sweep enumerated far fewer routes than the application has.');
    }

    /**
     * The partition proof: every route the application registers is either
     * behind `auth`, behind `guest`, the health check, or the fallback that
     * renders an unmatched URL's 404 through the web group (UAT F-6-08). Nothing
     * sits outside them, so the sweep above cannot miss a route by failing to
     * match it.
     *
     * This is why the sweep needs no hardcoded count — a new route joins one of
     * the three bands or fails here.
     */
    public function test_every_registered_route_is_authenticated_guest_only_or_the_health_check(): void
    {
        $unbanded = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $banded = in_array('auth', $middleware, true)
                || in_array('guest', $middleware, true)
                || $route->uri() === self::HEALTH_URI
                || $route->isFallback;

            if (! $banded) {
                $unbanded[] = $route->uri().' ('.($route->getName() ?? 'unnamed').')';
            }
        }

        $this->assertSame([], $unbanded, 'Routes sit outside the auth and guest bands: '.implode(', ', $unbanded));
    }

    /**
     * Route model binding never runs for a guest, so the sweep can use a dummy
     * id: Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests precedes
     * SubstituteBindings in the framework's middleware priority. This asserts
     * that ordering directly — /jobs/999999 redirects rather than 404s — because
     * the whole sweep above depends on it.
     */
    public function test_authentication_refuses_a_guest_before_a_model_is_resolved(): void
    {
        $this->get('/jobs/999999')->assertRedirect(route('login'));
        $this->get('/parts/999999')->assertRedirect(route('login'));
    }

    /**
     * @return list<RoutingRoute>
     */
    private function authenticatedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('auth', $route->gatherMiddleware(), true)) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    private function verbFor(RoutingRoute $route): string
    {
        $methods = array_diff($route->methods(), ['HEAD', 'OPTIONS']);

        return strtolower((string) reset($methods));
    }

    /**
     * Optional segments are dropped rather than filled, because a constrained
     * one cannot take a dummy value: `attachments/{attachment}/{variant?}` is
     * whereIn('thumb','full','original'), so "1" fails to match at the ROUTER
     * and returns a 404 that looks exactly like an unguarded route answering a
     * guest. Dropping the segment tests the same route through its default.
     */
    private function urlFor(RoutingRoute $route): string
    {
        $uri = (string) preg_replace('#/\{[^}]+\?\}#', '', $route->uri());
        $uri = (string) preg_replace('/\{[^}]+\}/', '1', $uri);

        return '/'.ltrim($uri, '/');
    }
}
