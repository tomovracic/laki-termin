<?php

use App\Models\Role;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function assignAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can open users and terrains admin overviews', function () {
    $admin = User::factory()->create(['token_count' => 100]);
    assignAdminRole($admin);

    User::factory()->create(['name' => 'User One', 'token_count' => 5]);
    Terrain::query()->create([
        'name' => 'Court A',
        'description' => 'Outdoor court',
        'code' => 'court-a',
        'is_active' => true,
    ]);
    TerrainSetting::query()->create([
        'terrain_id' => null,
        'is_global' => true,
        'max_advance_days' => 30,
        'availability_periods' => [
            [
                'from' => '08:00',
                'to' => '22:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->has('users', 2)
            ->where('users.0.invitation_status', 'active')
            ->where('users.1.invitation_status', 'active')
            ->where('users.0.reservations_count', 0)
            ->where('users.1.reservations_count', 0)
        );

    $this->actingAs($admin)
        ->get(route('admin.terrains'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/terrains')
            ->has('terrains', 1)
            ->where('global_setting.max_advance_days', 30),
        );
});

test('non-admin cannot open admin overviews', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.terrains'))
        ->assertForbidden();
});
