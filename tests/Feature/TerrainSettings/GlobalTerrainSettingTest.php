<?php

use App\Models\Role;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use App\Models\User;

function attachAdminRoleForSettings(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('terrain setting upsert creates global settings only', function () {
    $admin = User::factory()->create();
    attachAdminRoleForSettings($admin);

    $response = $this->actingAs($admin)->post(route('terrain-settings.upsert'), [
        'max_advance_days' => 30,
        'cancellation_cutoff_hours' => 6,
        'availability_periods' => [
            [
                'from' => '08:00',
                'to' => '22:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.cancellation_cutoff_hours', 6)
        ->assertJsonPath('data.availability_periods.0.from', '08:00')
        ->assertJsonPath('data.availability_periods.0.to', '22:00');

    $setting = TerrainSetting::query()->firstOrFail();
    expect($setting->terrain_id)->toBeNull();
    expect($setting->is_global)->toBeTrue();
});

test('terrain-specific setting input is rejected', function () {
    $admin = User::factory()->create();
    attachAdminRoleForSettings($admin);
    $terrain = Terrain::query()->create([
        'name' => 'Court A',
        'code' => 'court-a',
        'description' => null,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('terrain-settings.upsert'), [
        'terrain_id' => $terrain->id,
        'is_global' => false,
        'max_advance_days' => 30,
        'cancellation_cutoff_hours' => 0,
        'availability_periods' => [
            [
                'from' => '08:00',
                'to' => '22:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $response
        ->assertStatus(302)
        ->assertSessionHasErrors(['terrain_id', 'is_global']);
});

test('overlapping availability periods are rejected', function () {
    $admin = User::factory()->create();
    attachAdminRoleForSettings($admin);

    $response = $this->actingAs($admin)->post(route('terrain-settings.upsert'), [
        'max_advance_days' => 30,
        'cancellation_cutoff_hours' => 0,
        'availability_periods' => [
            [
                'from' => '08:00',
                'to' => '11:00',
                'slot_duration_minutes' => 60,
            ],
            [
                'from' => '10:00',
                'to' => '12:00',
                'slot_duration_minutes' => 60,
            ],
        ],
    ]);

    $response
        ->assertStatus(302)
        ->assertSessionHasErrors(['availability_periods.1.from']);
});

test('global settings accept 45 minute slot duration', function () {
    $admin = User::factory()->create();
    attachAdminRoleForSettings($admin);

    $response = $this->actingAs($admin)->post(route('terrain-settings.upsert'), [
        'max_advance_days' => 30,
        'cancellation_cutoff_hours' => 0,
        'availability_periods' => [
            [
                'from' => '12:00',
                'to' => '16:00',
                'slot_duration_minutes' => 45,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.availability_periods.0.slot_duration_minutes', 45);
});
