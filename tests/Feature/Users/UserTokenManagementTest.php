<?php

use App\Models\Role;
use App\Models\User;

function attachAdminRoleToUser(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can list all users with token counts', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);

    $firstUser = User::factory()->create(['token_count' => 10]);
    $secondUser = User::factory()->create(['token_count' => 25]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response
        ->assertOk()
        ->assertJsonFragment([
            'id' => $admin->id,
        ])
        ->assertJsonFragment([
            'id' => $firstUser->id,
            'token_count' => 10,
        ])
        ->assertJsonFragment([
            'id' => $secondUser->id,
            'token_count' => 25,
        ]);
});

test('admin can update user token count', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);
    $targetUser = User::factory()->create(['token_count' => 5]);

    $response = $this->actingAs($admin)->patch(route('users.token-count.update', $targetUser), [
        'token_count' => 42,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $targetUser->id)
        ->assertJsonPath('data.token_count', 42);

    expect($targetUser->fresh()->token_count)->toBe(42);
});

test('non-admin cannot access user token management', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create(['token_count' => 7]);

    $indexResponse = $this->actingAs($user)->get(route('users.index'));
    $updateResponse = $this->actingAs($user)->patch(route('users.token-count.update', $targetUser), [
        'token_count' => 20,
    ]);

    $indexResponse->assertForbidden();
    $updateResponse->assertForbidden();
});
