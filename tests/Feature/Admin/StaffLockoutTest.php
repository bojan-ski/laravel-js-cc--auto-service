<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Exceptions\AdminLockoutPrevented;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The three lockout rules live in one layer each, and the layers are reachable
 * by different means. Self-offboard is refused by UserPolicy (403) and
 * self-demotion by UpdateUserRequest (422) — both reachable over HTTP. The
 * zero-admin guard is NOT reachable over HTTP by construction: the acting admin
 * is always an active admin, so any *other* admin they act on leaves at least
 * one behind, and acting on themselves is already refused above. It is therefore
 * exercised directly against the service, with the controller's error-flash path
 * driven by a service stubbed to throw. See StaffConcurrencyTest for the lock.
 */
class StaffLockoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_admin_sees_edit_but_no_offboard_control_on_their_own_row(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Aleksandar Admin']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('staff.actions.edit', ['name' => 'Aleksandar Admin']))
            ->assertDontSee(__('staff.actions.offboard_named', ['name' => 'Aleksandar Admin']));
    }

    /**
     * The marker is what explains the missing Offboard control on that one row.
     * It is driven by the controller-supplied `currentUserId`, so it must land on
     * the acting admin's row and no other.
     */
    public function test_only_the_acting_admins_own_row_is_marked_as_theirs(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Aleksandar Admin']);
        User::factory()->admin()->create(['name' => 'Bojana Admin']);
        User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, '>'.__('staff.you').'<'));

        $ownRow = $this->rowFor($html, 'Aleksandar Admin');
        $otherRow = $this->rowFor($html, 'Bojana Admin');

        $this->assertStringContainsString(__('staff.you'), $ownRow);
        $this->assertStringNotContainsString(__('staff.you'), $otherRow);
        $this->assertStringNotContainsString(__('staff.actions.offboard'), $ownRow);
        $this->assertStringContainsString(__('staff.actions.offboard'), $otherRow);
    }

    private function rowFor(string $html, string $name): string
    {
        preg_match_all('/<tr\b.*?<\/tr>/s', $html, $rows);

        foreach ($rows[0] as $row) {
            if (str_contains($row, $name)) {
                return $row;
            }
        }

        $this->fail("No row rendered for {$name}.");
    }

    public function test_another_admins_row_by_contrast_does_carry_an_offboard_control(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Aleksandar Admin']);
        User::factory()->admin()->create(['name' => 'Bojana Admin']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('staff.actions.offboard_named', ['name' => 'Bojana Admin']));
    }

    public function test_an_admin_cannot_offboard_themselves_over_http(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.offboard', $admin))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_the_own_edit_form_shows_the_role_as_text_rather_than_a_select(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertOk();

        $response->assertSee(__('staff.form.own_role_note'));
        $response->assertDontSee('name="role"', false);
        $response->assertSee(UserRole::Admin->label());
    }

    public function test_another_users_edit_form_by_contrast_does_offer_a_role_select(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $member))
            ->assertOk()
            ->assertSee('name="role"', false)
            ->assertDontSee(__('staff.form.own_role_note'));
    }

    public function test_a_submitted_role_on_your_own_account_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Renamed Myself',
            'email' => $admin->email,
            'role' => 'mechanic',
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    /**
     * `prohibited` rejects ANY present value, including the role they already
     * hold. A weaker "must equal your current role" rule would accept this, and
     * would then be one edit away from permitting a change.
     */
    public function test_resubmitting_your_own_current_role_is_still_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
        ])->assertSessionHasErrors('role');
    }

    /**
     * An array payload is not "empty" under the `prohibited` rule, so it must be
     * refused rather than silently discarded on the one field that decides
     * authority.
     */
    public function test_an_array_payload_cannot_smuggle_a_role_onto_your_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => ['mechanic'],
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_an_admin_may_still_rename_themselves_without_touching_their_role(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'aleks@shop.rs']);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Aleksandar Renamed',
            'email' => 'aleks@shop.rs',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $admin->refresh();

        $this->assertSame('Aleksandar Renamed', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_the_service_refuses_to_offboard_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(AdminLockoutPrevented::class);

        try {
            app(StaffService::class)->offboard($admin);
        } finally {
            $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
        }
    }

    public function test_the_service_refuses_to_demote_the_last_active_admin(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Only Admin']);

        $this->expectException(AdminLockoutPrevented::class);

        try {
            app(StaffService::class)->update($admin, [
                'name' => 'Only Admin',
                'email' => $admin->email,
            ], UserRole::Mechanic);
        } finally {
            $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        }
    }

    public function test_an_offboarded_admin_does_not_count_towards_the_survivor(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->trashed()->create();

        $this->expectException(AdminLockoutPrevented::class);

        app(StaffService::class)->offboard($admin);
    }

    public function test_a_non_admin_can_always_be_offboarded_however_few_admins_remain(): void
    {
        User::factory()->admin()->create();
        $mechanic = User::factory()->mechanic()->create();

        app(StaffService::class)->offboard($mechanic);

        $this->assertSoftDeleted('users', ['id' => $mechanic->id]);
    }

    public function test_with_two_admins_one_may_offboard_the_other_and_the_survivor_then_cannot_leave(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        $this->actingAs($first)->delete(route('admin.users.destroy', $second))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $second->id]);
        $this->assertSame(1, User::query()->activeAdmins()->count());

        $this->actingAs($first)->delete(route('admin.users.destroy', $first))->assertForbidden();

        $this->expectException(AdminLockoutPrevented::class);
        app(StaffService::class)->offboard($first);
    }

    /**
     * The guard cannot be provoked over HTTP (see the class docblock), so the
     * controller's recovery path is driven by a service stubbed to throw. What
     * this pins is the controller: it catches, flashes, and writes nothing.
     */
    public function test_the_offboard_controller_turns_the_refusal_into_an_error_flash(): void
    {
        $this->instance(StaffService::class, new class extends StaffService
        {
            public function offboard(User $user): void
            {
                throw new AdminLockoutPrevented($user, 'stubbed for the controller path');
            }
        });

        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('staff.flash.last_admin'))
            ->assertSessionMissing('success');

        $this->assertNotSoftDeleted('users', ['id' => $other->id]);
    }

    public function test_the_update_controller_turns_the_refusal_into_an_error_flash(): void
    {
        $this->instance(StaffService::class, new class extends StaffService
        {
            public function update(User $user, array $attributes, ?UserRole $role = null): User
            {
                throw new AdminLockoutPrevented($user, 'stubbed for the controller path');
            }
        });

        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create(['name' => 'Bojana Admin']);

        $this->actingAs($admin)->put(route('admin.users.update', $other), [
            'name' => 'Bojana Admin',
            'email' => $other->email,
            'role' => 'mechanic',
        ])
            ->assertRedirect(route('admin.users.edit', $other))
            ->assertSessionHas('error', __('staff.flash.last_admin'))
            ->assertSessionMissing('success');

        $this->assertSame(UserRole::Admin, $other->fresh()->role);
    }
}
