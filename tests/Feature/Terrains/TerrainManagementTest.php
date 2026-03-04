<?php

use App\Models\Role;
use App\Models\Terrain;
use App\Models\User;

function attachAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can create terrain with name and description', function () {
    $admin = User::factory()->create();
    attachAdminRole($admin);

    $response = $this->actingAs($admin)->post(route('terrains.store'), [
        'name' => 'Court A',
        'description' => 'Main outdoor terrain.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Court A')
        ->assertJsonPath('data.description', 'Main outdoor terrain.')
        ->assertJsonPath('data.code', 'court-a')
        ->assertJsonPath('data.is_active', true);

    $terrain = Terrain::query()->firstOrFail();
    expect($terrain->name)->toBe('Court A');
    expect($terrain->description)->toBe('Main outdoor terrain.');
    expect($terrain->code)->toBe('court-a');
    expect($terrain->is_active)->toBeTrue();
});

test('non-admin user cannot create terrain', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('terrains.store'), [
        'name' => 'Court B',
        'description' => 'Second terrain.',
    ]);

    $response->assertForbidden();
});

test('terrain code is generated uniquely from terrain name', function () {
    $admin = User::factory()->create();
    attachAdminRole($admin);

    Terrain::query()->create([
        'name' => 'Court A',
        'description' => 'Existing terrain',
        'code' => 'court-a',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('terrains.store'), [
        'name' => 'Court A',
        'description' => 'Another one with same name.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.code', 'court-a-2');
});
