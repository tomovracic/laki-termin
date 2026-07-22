<?php

use App\Enums\UserInvitationStatus;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;

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

test('admin can create a new user with groups', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);
    Notification::fake();
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'email' => 'ana.admin@example.com',
        'group_ids' => [$groupA->id, $groupB->id],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'ana.admin@example.com')
        ->assertJsonPath('data.token_count', 0);

    $createdUser = User::query()->where('email', 'ana.admin@example.com')->first();

    expect($createdUser)->not()->toBeNull();
    expect($createdUser?->token_count)->toBe(0);
    expect($createdUser?->isPendingInvitation())->toBeTrue();
    expect($createdUser?->invitation_token_hash)->not()->toBeNull();
    expect($createdUser?->invitation_expires_at)->not()->toBeNull();
    expect($createdUser?->invitation_expires_at?->greaterThan(now()->addDays(2)))->toBeTrue();
    expect($createdUser?->invitation_expires_at?->lessThan(now()->addDays(4)))->toBeTrue();
    expect($createdUser?->groups()->pluck('groups.id')->sort()->values()->all())
        ->toBe(collect([$groupA->id, $groupB->id])->sort()->values()->all());

    Notification::assertSentTo($createdUser, UserInvitationNotification::class);
});

test('admin can resend invitation for existing pending user', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);
    Notification::fake();
    $group = Group::factory()->create();

    $pendingUser = User::factory()->create([
        'email' => 'pending@example.com',
        'invitation_status' => UserInvitationStatus::Pending->value,
        'invitation_token_hash' => hash('sha256', 'old-token'),
        'invited_at' => now()->subDay(),
        'invitation_expires_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'email' => 'pending@example.com',
        'group_ids' => [$group->id],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $pendingUser->id);

    $pendingUser->refresh();
    expect($pendingUser->isPendingInvitation())->toBeTrue();
    expect($pendingUser->invitation_expires_at?->isFuture())->toBeTrue();
    expect($pendingUser->groups()->pluck('groups.id')->all())->toBe([$group->id]);

    Notification::assertSentTo($pendingUser, UserInvitationNotification::class);
});

test('admin cannot create invitation for existing active user email', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);
    Notification::fake();
    $group = Group::factory()->create();

    User::factory()->create([
        'email' => 'active@example.com',
        'invitation_status' => UserInvitationStatus::Active->value,
    ]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'email' => 'active@example.com',
        'group_ids' => [$group->id],
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHasErrors(['email']);

    Notification::assertNothingSent();
});

test('admin can update user groups', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);
    $targetUser = User::factory()->create();
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $targetUser->groups()->attach($groupA->id);

    $response = $this->actingAs($admin)->patch(route('users.groups.update', $targetUser), [
        'group_ids' => [$groupB->id],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $targetUser->id);

    expect($targetUser->fresh()->groups()->pluck('groups.id')->all())->toBe([$groupB->id]);
});

test('creating a user requires at least one group', function () {
    $admin = User::factory()->create();
    attachAdminRoleToUser($admin);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'email' => 'nogroups@example.com',
        'group_ids' => [],
    ]);

    $response->assertRedirect()->assertSessionHasErrors(['group_ids']);
});

test('non-admin cannot create a new user', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    $response = $this->actingAs($user)->post(route('users.store'), [
        'email' => 'una.user@example.com',
        'group_ids' => [$group->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', [
        'email' => 'una.user@example.com',
    ]);
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
