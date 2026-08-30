<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function abilityProvider(): array
    {
        return [
            'mechanic' => ['mechanic', [
                'viewAny' => true,
                'view' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'restore' => false,
                'viewDeleted' => false,
                'viewPricing' => false,
                'viewCostPrice' => false,
            ]],
            'receptionist' => ['receptionist', [
                'viewAny' => true,
                'view' => true,
                'create' => true,
                'update' => true,
                'delete' => false,
                'restore' => false,
                'viewDeleted' => false,
                'viewPricing' => true,
                'viewCostPrice' => false,
            ]],
            'admin' => ['admin', [
                'viewAny' => true,
                'view' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'restore' => true,
                'viewDeleted' => true,
                'viewPricing' => true,
                'viewCostPrice' => true,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('abilityProvider')]
    public function test_the_policy_matches_the_authorization_matrix(string $role, array $expected): void
    {
        $user = User::factory()->{$role}()->create();
        $part = Part::factory()->create();

        foreach ($expected as $ability => $allowed) {
            $subject = in_array($ability, ['view', 'update', 'delete', 'restore'], true) ? $part : Part::class;

            $this->assertSame(
                $allowed,
                Gate::forUser($user)->allows($ability, $subject),
                "{$role} should ".($allowed ? 'be granted' : 'be denied')." {$ability}."
            );
        }
    }

    public function test_cost_price_is_denied_to_a_receptionist_and_granted_to_an_admin(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->receptionist()->create())->allows('viewCostPrice', Part::class));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewCostPrice', Part::class));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routeProvider(): array
    {
        return [
            'index' => ['get', 'parts.index'],
            'create' => ['get', 'parts.create'],
            'store' => ['post', 'parts.store'],
            'show' => ['get', 'parts.show'],
            'edit' => ['get', 'parts.edit'],
            'update' => ['put', 'parts.update'],
            'destroy' => ['delete', 'parts.destroy'],
            'restore' => ['patch', 'parts.restore'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_a_guest_is_redirected_to_login(string $method, string $name): void
    {
        $part = Part::factory()->create();
        $url = in_array($name, ['parts.index', 'parts.create', 'parts.store'], true)
            ? route($name)
            : route($name, $part);

        $this->{$method}($url)->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mechanicForbiddenProvider(): array
    {
        return [
            'create' => ['get', 'parts.create'],
            'store' => ['post', 'parts.store'],
            'edit' => ['get', 'parts.edit'],
            'update' => ['put', 'parts.update'],
            'destroy' => ['delete', 'parts.destroy'],
            'restore' => ['patch', 'parts.restore'],
        ];
    }

    /**
     * A valid payload is sent so the refusal is the policy's and not the Form
     * Request's — validation runs first and would mask a missing authorize call.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'sku' => 'OF-3000',
            'name' => 'Oil filter',
            'unit_price' => '850.00',
            'reorder_level' => 4,
        ];
    }

    #[DataProvider('mechanicForbiddenProvider')]
    public function test_a_mechanic_is_refused_every_write(string $method, string $name): void
    {
        $part = Part::factory()->create();
        $url = in_array($name, ['parts.create', 'parts.store'], true) ? route($name) : route($name, $part);

        $this->actingAs(User::factory()->mechanic()->create())
            ->{$method}($url, $this->payload())
            ->assertForbidden();
    }

    public function test_a_mechanic_may_browse_and_open_a_part(): void
    {
        $part = Part::factory()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('parts.index'))->assertOk();
        $this->actingAs($mechanic)->get(route('parts.show', $part))->assertOk();
    }

    public function test_a_receptionist_may_create_and_update_but_not_delete_or_restore(): void
    {
        $part = Part::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get(route('parts.create'))->assertOk();
        $this->actingAs($receptionist)->get(route('parts.edit', $part))->assertOk();
        $this->assertFalse($receptionist->can('delete', $part));
        $this->assertFalse($receptionist->can('restore', $part));

        $this->actingAs($receptionist)->delete(route('parts.destroy', $part))->assertForbidden();
        $this->actingAs($receptionist)->patch(route('parts.restore', $part))->assertForbidden();
    }

    public function test_the_create_route_is_not_swallowed_by_the_show_route(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/parts/create')
            ->assertOk()
            ->assertSee(__('parts.create.title'));
    }
}
