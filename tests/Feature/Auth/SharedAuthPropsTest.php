<?php

use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function attachAdminRoleForSharedAuthProps(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('shared auth props mark admin users as admins', function () {
    $user = User::factory()->create();
    attachAdminRoleForSharedAuthProps($user);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));
});

test('shared auth props mark non-admin users as non-admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
});
