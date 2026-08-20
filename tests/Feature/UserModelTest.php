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
}
