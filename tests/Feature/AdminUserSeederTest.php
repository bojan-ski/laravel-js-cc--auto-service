<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<string, string|null> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
            $value = Env::getRepository()->get($key);
            $this->originalEnv[$key] = is_string($value) ? $value : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $value === null
                ? Env::getRepository()->clear($key)
                : Env::getRepository()->set($key, $value);
        }

        parent::tearDown();
    }

    private function withAdminEnv(?string $password, string $email = 'admin@auto-service.test'): void
    {
        Env::getRepository()->set('ADMIN_EMAIL', $email);

        $password === null
            ? Env::getRepository()->clear('ADMIN_PASSWORD')
            : Env::getRepository()->set('ADMIN_PASSWORD', $password);
    }

    public function test_it_creates_one_administrator(): void
    {
        $this->withAdminEnv('seeded-admin-password');

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserRole::Admin, User::first()->role);
    }

    public function test_the_seeded_password_is_hashed(): void
    {
        $this->withAdminEnv('seeded-admin-password');

        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('seeded-admin-password', User::first()->password));
    }

    public function test_running_it_twice_creates_only_one_account(): void
    {
        $this->withAdminEnv('seeded-admin-password');

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_re_run_does_not_reset_an_existing_password(): void
    {
        $this->withAdminEnv('seeded-admin-password');
        $this->seed(AdminUserSeeder::class);

        $this->withAdminEnv('a-different-password');
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('seeded-admin-password', User::first()->password));
    }

    public function test_it_aborts_when_the_password_variable_is_absent(): void
    {
        $this->withAdminEnv(null);

        $this->expectException(RuntimeException::class);

        try {
            $this->seed(AdminUserSeeder::class);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_a_mixed_case_address_is_stored_lowercased(): void
    {
        $this->withAdminEnv('seeded-admin-password', '  Admin@Auto-Service.TEST  ');

        $this->seed(AdminUserSeeder::class);

        $this->assertSame('admin@auto-service.test', User::first()->email);
    }
}
