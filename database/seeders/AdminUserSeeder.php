<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if (blank($password)) {
            throw new RuntimeException(
                'ADMIN_PASSWORD is not set. Set it in your .env before seeding the first administrator.'
            );
        }

        $email = Str::lower(trim((string) env('ADMIN_EMAIL', 'admin@auto-service.test')));

        if (User::where('email', $email)->exists()) {
            return;
        }

        $user = new User;
        $user->name = 'Administrator';
        $user->email = $email;
        $user->role = UserRole::Admin;
        $user->password = $password;
        $user->save();
    }
}
