<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every route in the section, as [method, route name, needs a target].
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function routeProvider(): array
    {
        return [
            'index' => ['get', 'admin.users.index', false],
            'create' => ['get', 'admin.users.create', false],
            'store' => ['post', 'admin.users.store', false],
            'edit' => ['get', 'admin.users.edit', true],
            'offboard' => ['get', 'admin.users.offboard', true],
            'update' => ['put', 'admin.users.update', true],
            'destroy' => ['delete', 'admin.users.destroy', true],
            'restore' => ['patch', 'admin.users.restore', true],
        ];
    }

    private function url(string $name, bool $needsTarget): string
    {
        return $needsTarget
            ? route($name, User::factory()->mechanic()->create())
            : route($name);
    }

    #[DataProvider('routeProvider')]
    public function test_a_guest_is_redirected_to_login(string $method, string $name, bool $needsTarget): void
    {
        $this->{$method}($this->url($name, $needsTarget))->assertRedirect(route('login'));
    }

    #[DataProvider('routeProvider')]
    public function test_a_mechanic_is_refused(string $method, string $name, bool $needsTarget): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->{$method}($this->url($name, $needsTarget))
            ->assertForbidden();
    }

    #[DataProvider('routeProvider')]
    public function test_a_receptionist_is_refused(string $method, string $name, bool $needsTarget): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->{$method}($this->url($name, $needsTarget))
            ->assertForbidden();
    }

    public function test_an_admin_reaches_every_page_in_the_section(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create();
        $offboarded = User::factory()->mechanic()->trashed()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $member))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.offboard', $member))->assertOk();

        $this->assertTrue($offboarded->isOffboarded());
    }

    public function test_every_write_action_completes_and_redirects_to_the_index(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jelena Marković',
            'email' => 'jelena@shop.rs',
            'role' => 'receptionist',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'name' => 'Dragan Jovanović',
            'email' => 'dragan@shop.rs',
            'role' => 'mechanic',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $this->actingAs($admin)->patch(route('admin.users.restore', $member))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('success');
    }

    public function test_the_create_route_is_not_bound_as_a_user(): void
    {
        User::factory()->mechanic()->create(['name' => 'Someone Else']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee(__('staff.create.title'))
            ->assertDontSee('Someone Else');
    }
}
