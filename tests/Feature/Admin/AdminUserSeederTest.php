<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;

test('admin user seeder creates admin user and assigns admin role', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@mail.com')->first();

    expect($admin)->not()->toBeNull();
    expect($admin?->name)->toBe('Admin User');
    expect($admin?->roles()->where('name', 'admin')->exists())->toBeTrue();
    expect(Role::query()->where('name', 'admin')->exists())->toBeTrue();
});

test('admin user seeder is idempotent', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@mail.com')->firstOrFail();
    $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

    expect(User::query()->where('email', 'admin@mail.com')->count())->toBe(1);
    expect(Role::query()->where('name', 'admin')->count())->toBe(1);
    expect($admin->roles()->whereKey($adminRole->id)->count())->toBe(1);
});
