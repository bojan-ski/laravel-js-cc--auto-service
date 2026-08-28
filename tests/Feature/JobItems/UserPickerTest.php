<?php

namespace Tests\Feature\JobItems;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_options_are_keyed_by_id_labelled_with_the_full_name_and_ordered_by_name(): void
    {
        $zora = User::factory()->mechanic()->create(['name' => 'Zora Ilić']);
        $ana = User::factory()->receptionist()->create(['name' => 'Ana Petrov']);
        $milan = User::factory()->admin()->create(['name' => 'Milan Kovač']);

        $options = app(UserService::class)->pickerOptions();

        $this->assertSame([$ana->getKey(), $milan->getKey(), $zora->getKey()], array_keys($options));
        $this->assertSame('Ana Petrov', $options[$ana->getKey()]);
    }

    public function test_every_active_role_is_offered_not_only_mechanics(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $receptionist = User::factory()->receptionist()->create();
        $admin = User::factory()->admin()->create();

        $options = app(UserService::class)->pickerOptions();

        $this->assertArrayHasKey($mechanic->getKey(), $options);
        $this->assertArrayHasKey($receptionist->getKey(), $options);
        $this->assertArrayHasKey($admin->getKey(), $options);
    }

    public function test_soft_deleted_users_are_excluded(): void
    {
        $kept = User::factory()->mechanic()->create();
        $departed = User::factory()->mechanic()->create();
        $departed->delete();

        $options = app(UserService::class)->pickerOptions();

        $this->assertArrayHasKey($kept->getKey(), $options);
        $this->assertArrayNotHasKey($departed->getKey(), $options);
    }
}
