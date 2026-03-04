<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user and ensure admin role assignment.
     */
    public function run(): void
    {
        $adminUser = User::query()->updateOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'phone' => '0910000000',
                'password' => 'alfa1111',
            ],
        );

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'System administrator'],
        );

        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
