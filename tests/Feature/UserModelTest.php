<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_users_table_has_an_indexed_role_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $indexed = collect(Schema::getIndexes('users'))
            ->contains(fn (array $index) => $index['columns'] === ['role']);

        $this->assertTrue($indexed);
    }

    public function test_the_users_table_supports_soft_deletes(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
    }

    public function test_the_users_table_has_no_email_verified_at_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
    }

    public function test_role_casts_to_the_user_role_enum(): void
    {
        $user = User::factory()->receptionist()->create();

        $this->assertSame(UserRole::Receptionist, $user->fresh()->role);
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = new User;
        $user->fill(['name' => 'Someone', 'role' => UserRole::Admin->value]);

        $this->assertNull($user->role);
    }

    public function test_the_password_is_hashed_on_save(): void
    {
        $user = User::factory()->create(['password' => 'a-plain-password']);

        $this->assertNotSame('a-plain-password', $user->password);
        $this->assertTrue(Hash::check('a-plain-password', $user->password));
    }

    public function test_a_soft_deleted_user_is_excluded_from_default_queries(): void
    {
        $user = User::factory()->trashed()->create();

        $this->assertNull(User::find($user->id));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_is_offboarded_reports_the_soft_delete(): void
    {
        $this->assertFalse(User::factory()->create()->isOffboarded());
        $this->assertTrue(User::factory()->trashed()->create()->isOffboarded());
    }

    public function test_active_admins_excludes_other_roles(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->receptionist()->create();
        User::factory()->mechanic()->create();

        $this->assertSame([$admin->id], User::query()->activeAdmins()->pluck('id')->all());
    }

    /**
     * The scope carries its own `deleted_at is null` rather than relying on the
     * SoftDeletingScope, because the staff list applies withTrashed() — which
     * removes that scope from the builder. Without the explicit clause the
     * lockout guard would count offboarded admins as survivors.
     */
    public function test_active_admins_excludes_trashed_admins_even_under_with_trashed(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->trashed()->create();

        $this->assertSame([$admin->id], User::withTrashed()->activeAdmins()->pluck('id')->all());
        $this->assertSame(2, User::withTrashed()->where('role', UserRole::Admin)->count());
    }

    public function test_matching_searches_name_or_email(): void
    {
        $byName = User::factory()->create(['name' => 'Dragan Jovanovic', 'email' => 'dj@shop.rs']);
        $byEmail = User::factory()->create(['name' => 'Milan Petrov', 'email' => 'jovanovic@shop.rs']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@shop.rs']);

        $found = User::query()->matching('jovanovic')->pluck('id')->all();

        $this->assertCount(2, $found);
        $this->assertContains($byName->id, $found);
        $this->assertContains($byEmail->id, $found);
    }

    public function test_matching_treats_wildcards_as_literal_characters(): void
    {
        User::factory()->create(['name' => 'Percent Free', 'email' => 'free@shop.rs']);
        $literal = User::factory()->create(['name' => 'Fifty%_Off', 'email' => 'fifty@shop.rs']);

        $this->assertSame([$literal->id], User::query()->matching('%_')->pluck('id')->all());
    }
}
