<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_name_email_and_role_can_all_be_changed_on_another_user(): void
    {
        $member = User::factory()->mechanic()->create([
            'name' => 'Old Name',
            'email' => 'old@shop.rs',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => 'New Name',
                'email' => 'new@shop.rs',
                'role' => 'receptionist',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $member->refresh();

        $this->assertSame('New Name', $member->name);
        $this->assertSame('new@shop.rs', $member->email);
        $this->assertSame(UserRole::Receptionist, $member->role);
    }

    public function test_an_empty_password_leaves_the_stored_hash_untouched(): void
    {
        $member = User::factory()->mechanic()->create(['password' => 'the-original-password']);
        $before = $member->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => 'Renamed Only',
                'email' => $member->email,
                'role' => 'mechanic',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($before, $member->fresh()->password);
        $this->assertTrue(Hash::check('the-original-password', $member->fresh()->password));
    }

    public function test_an_omitted_password_field_also_leaves_the_hash_untouched(): void
    {
        $member = User::factory()->mechanic()->create(['password' => 'the-original-password']);
        $before = $member->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'mechanic',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($before, $member->fresh()->password);
    }

    public function test_a_supplied_password_replaces_the_old_one(): void
    {
        $member = User::factory()->mechanic()->create(['password' => 'the-original-password']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'mechanic',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertRedirect(route('admin.users.index'));

        // /login sits behind the guest band, so the admin's session must go first.
        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => $member->email, 'password' => 'the-original-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $member->email, 'password' => 'a-brand-new-password']);
        $this->assertAuthenticatedAs($member->fresh());
    }

    /**
     * Sessions re-resolve the user from the database on every request, so a role
     * change lands without re-authenticating. The promotion is done through the
     * service rather than a second HTTP call so that one session stays signed in
     * as the target throughout.
     */
    public function test_a_role_change_takes_effect_on_the_targets_next_request(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->post('/login', ['email' => $member->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($member);

        $this->get(route('invoices.index'))->assertForbidden();

        app(StaffService::class)->update($member, [
            'name' => $member->name,
            'email' => $member->email,
        ], UserRole::Receptionist);

        Auth::forgetGuards();

        $this->get(route('invoices.index'))->assertOk();
    }

    public function test_submitting_an_unchanged_email_is_not_a_conflict_with_yourself(): void
    {
        $member = User::factory()->mechanic()->create(['email' => 'keep@shop.rs']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => 'Renamed',
                'email' => 'keep@shop.rs',
                'role' => 'mechanic',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_moving_to_an_address_another_active_user_holds_is_refused(): void
    {
        User::factory()->mechanic()->create(['email' => 'taken@shop.rs']);
        $member = User::factory()->mechanic()->create(['email' => 'mine@shop.rs']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => 'taken@shop.rs',
                'role' => 'mechanic',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('mine@shop.rs', $member->fresh()->email);
    }

    public function test_moving_to_an_offboarded_address_reports_the_distinct_message(): void
    {
        User::factory()->mechanic()->trashed()->create([
            'name' => 'Nikola Stankovic',
            'email' => 'nikola@shop.rs',
        ]);
        $member = User::factory()->mechanic()->create(['email' => 'mine@shop.rs']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => 'nikola@shop.rs',
                'role' => 'mechanic',
            ])
            ->assertInvalid([
                'email' => __('staff.validation.email_offboarded', ['name' => 'Nikola Stankovic']),
            ]);
    }

    public function test_an_unknown_role_is_refused_on_update(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'owner',
            ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Mechanic, $member->fresh()->role);
    }

    public function test_editing_an_offboarded_account_is_not_found(): void
    {
        $gone = User::factory()->mechanic()->trashed()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $gone))->assertNotFound();

        $this->actingAs($admin)->put(route('admin.users.update', $gone), [
            'name' => 'Whatever',
            'email' => $gone->email,
            'role' => 'mechanic',
        ])->assertNotFound();
    }

    public function test_the_edit_form_is_prefilled_and_never_echoes_a_password(): void
    {
        $member = User::factory()->receptionist()->create([
            'name' => 'Jelena Markovic',
            'email' => 'jelena@shop.rs',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $member))
            ->assertOk();

        $response->assertSee('value="Jelena Markovic"', false);
        $response->assertSee('value="jelena@shop.rs"', false);
        $response->assertSee(__('staff.form.password_note'));
        $response->assertDontSee('value="'.$member->password.'"', false);
    }

    public function test_the_edit_form_spoofs_the_put_method(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $member))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);
    }
}
