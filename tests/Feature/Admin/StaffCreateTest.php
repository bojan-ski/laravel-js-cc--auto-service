<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffCreateTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jelena Markovic',
            'email' => 'jelena@shop.rs',
            'role' => 'receptionist',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ], $overrides);
    }

    public function test_it_creates_an_account_with_the_chosen_role(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'jelena@shop.rs')->sole();

        $this->assertSame('Jelena Markovic', $created->name);
        $this->assertSame(UserRole::Receptionist, $created->role);
    }

    public function test_the_new_account_can_sign_in_immediately(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload());

        // /login sits behind the guest band, so the admin's session must go first.
        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', [
            'email' => 'jelena@shop.rs',
            'password' => 'correct-horse-battery',
        ])->assertRedirect();

        $this->assertAuthenticatedAs(User::where('email', 'jelena@shop.rs')->sole());
    }

    /**
     * The service takes the role as a parameter and assigns it after fill(), so
     * a role smuggled into the attribute array cannot decide authority. This
     * pins the PARAMETER contract — deleting `$user->role = $role` fails it.
     * `role`'s absence from #[Fillable] is pinned separately by
     * UserModelTest::test_role_is_not_mass_assignable.
     */
    public function test_the_service_takes_the_role_from_its_parameter_not_the_attribute_array(): void
    {
        $created = app(StaffService::class)->create([
            'name' => 'Smuggler',
            'email' => 'smuggler@shop.rs',
            'password' => 'correct-horse-battery',
            'role' => UserRole::Admin->value,
        ], UserRole::Mechanic);

        $this->assertSame(UserRole::Mechanic, $created->fresh()->role);
    }

    public function test_the_password_is_hashed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload());

        $created = User::where('email', 'jelena@shop.rs')->sole();

        $this->assertNotSame('correct-horse-battery', $created->password);
        $this->assertTrue(Hash::check('correct-horse-battery', $created->password));
    }

    public function test_a_password_below_the_framework_default_length_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload([
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'jelena@shop.rs']);
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload([
                'password_confirmation' => 'something-else-entirely',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['role' => 'owner']))
            ->assertSessionHasErrors('role');
    }

    public function test_a_duplicate_of_an_active_account_is_refused(): void
    {
        User::factory()->mechanic()->create(['name' => 'Milan Petrov', 'email' => 'taken@shop.rs']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['email' => 'taken@shop.rs']));

        $response->assertInvalid(['email' => __('validation.unique', ['attribute' => 'email'])]);

        $this->assertNotSame(
            __('staff.validation.email_offboarded', ['name' => 'Milan Petrov']),
            session('errors')->first('email'),
            'An active conflict must not claim the colleague is offboarded.'
        );

        $this->assertSame(1, User::where('email', 'taken@shop.rs')->count());
    }

    public function test_a_duplicate_of_an_offboarded_account_is_refused_with_a_distinct_message(): void
    {
        User::factory()->mechanic()->trashed()->create([
            'name' => 'Nikola Stankovic',
            'email' => 'nikola@shop.rs',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['email' => 'nikola@shop.rs']))
            ->assertInvalid([
                'email' => __('staff.validation.email_offboarded', ['name' => 'Nikola Stankovic']),
            ]);

        $this->assertSame(1, User::withTrashed()->where('email', 'nikola@shop.rs')->count());
    }

    public function test_the_email_is_lowercased_and_trimmed_before_it_is_stored(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['email' => '  JELENA@Shop.RS  ']))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'jelena@shop.rs']);
    }

    public function test_a_mixed_case_duplicate_of_an_offboarded_account_is_still_caught(): void
    {
        User::factory()->mechanic()->trashed()->create([
            'name' => 'Nikola Stankovic',
            'email' => 'nikola@shop.rs',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $this->payload(['email' => 'NIKOLA@SHOP.RS']))
            ->assertInvalid([
                'email' => __('staff.validation.email_offboarded', ['name' => 'Nikola Stankovic']),
            ]);
    }

    public function test_the_form_renders_a_role_choice_for_every_role(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create'))
            ->assertOk();

        foreach (UserRole::cases() as $role) {
            $response->assertSee('value="'.$role->value.'"', false);
            $response->assertSee($role->label());
        }
    }
}
