<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StaffRestoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_admin_can_restore_an_offboarded_account(): void
    {
        $gone = User::factory()->receptionist()->trashed()->create(['name' => 'Nikola Stankovic']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.users.restore', $gone))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('users', ['id' => $gone->id]);
        $this->assertFalse($gone->fresh()->isOffboarded());
    }

    public function test_the_restored_person_can_sign_in_again(): void
    {
        $gone = User::factory()->mechanic()->trashed()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.users.restore', $gone));

        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => $gone->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($gone->fresh());
    }

    public function test_restoring_preserves_the_original_role_and_the_password(): void
    {
        $gone = User::factory()->receptionist()->trashed()->create();
        $hash = $gone->password;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.users.restore', $gone));

        $restored = $gone->fresh();

        $this->assertSame(UserRole::Receptionist, $restored->role);
        $this->assertSame($hash, $restored->password);
    }

    public function test_a_restored_address_conflicts_as_an_active_account_again(): void
    {
        $gone = User::factory()->mechanic()->trashed()->create([
            'name' => 'Nikola Stankovic',
            'email' => 'nikola@shop.rs',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone New',
            'email' => 'nikola@shop.rs',
            'role' => 'mechanic',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertInvalid([
            'email' => __('staff.validation.email_offboarded', ['name' => 'Nikola Stankovic']),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.restore', $gone));

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone New',
            'email' => 'nikola@shop.rs',
            'role' => 'mechanic',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertNotSame(
            __('staff.validation.email_offboarded', ['name' => 'Nikola Stankovic']),
            session('errors')->first('email'),
            'Once restored, the conflict is an ordinary active one.'
        );
    }

    public function test_restoring_an_account_that_is_not_offboarded_is_refused(): void
    {
        $active = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.users.restore', $active))
            ->assertForbidden();
    }

    public function test_the_restore_control_appears_only_on_offboarded_rows(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->mechanic()->create(['name' => 'Active Person']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee(__('staff.actions.restore'));

        User::factory()->mechanic()->trashed()->create(['name' => 'Gone Person']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('staff.actions.restore'));
    }
}
