<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class RolePredicatesTest extends TestCase
{
    public function test_is_mechanic_is_true_only_for_a_mechanic(): void
    {
        $this->assertTrue(User::factory()->mechanic()->make()->isMechanic());
        $this->assertFalse(User::factory()->receptionist()->make()->isMechanic());
        $this->assertFalse(User::factory()->admin()->make()->isMechanic());
    }

    public function test_is_receptionist_is_true_only_for_a_receptionist(): void
    {
        $this->assertTrue(User::factory()->receptionist()->make()->isReceptionist());
        $this->assertFalse(User::factory()->mechanic()->make()->isReceptionist());
        $this->assertFalse(User::factory()->admin()->make()->isReceptionist());
    }

    public function test_is_admin_is_true_only_for_an_admin(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->mechanic()->make()->isAdmin());
        $this->assertFalse(User::factory()->receptionist()->make()->isAdmin());
    }

    public function test_is_front_office_covers_receptionist_and_admin_but_not_mechanic(): void
    {
        $this->assertTrue(User::factory()->receptionist()->make()->isFrontOffice());
        $this->assertTrue(User::factory()->admin()->make()->isFrontOffice());
        $this->assertFalse(User::factory()->mechanic()->make()->isFrontOffice());
    }

    public function test_the_enum_answers_front_office_for_receptionist_and_admin(): void
    {
        $this->assertTrue(UserRole::Receptionist->isFrontOffice());
        $this->assertTrue(UserRole::Admin->isFrontOffice());
        $this->assertFalse(UserRole::Mechanic->isFrontOffice());
    }

    public function test_a_user_without_a_role_is_not_front_office(): void
    {
        $this->assertFalse((new User)->isFrontOffice());
    }

    public function test_every_role_has_a_translated_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame(__('app.roles.'.$role->value), $role->label());
            $this->assertNotSame('app.roles.'.$role->value, $role->label());
        }
    }
}
