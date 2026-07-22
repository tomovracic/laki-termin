<?php

use App\Enums\GroupColor;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function attachAdminRoleForGroups(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

test('admin can view groups overview page', function () {
    $admin = User::factory()->create();
    attachAdminRoleForGroups($admin);

    $group = Group::factory()->create([
        'name' => 'A grupa',
        'color' => GroupColor::Blue->value,
        'can_access_ranking' => true,
        'can_view_all_ranking_groups' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.groups'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/groups')
            ->has('groups', 1)
            ->where('groups.0.id', $group->id)
            ->where('groups.0.name', 'A grupa')
            ->has('color_options'));
});

test('non-admin cannot view groups overview page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.groups'))
        ->assertForbidden();
});

test('admin can create a group', function () {
    $admin = User::factory()->create();
    attachAdminRoleForGroups($admin);

    $response = $this->actingAs($admin)->post(route('groups.store'), [
        'name' => 'Juniors',
        'color' => GroupColor::Emerald->value,
        'can_access_ranking' => true,
        'can_view_all_ranking_groups' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Juniors')
        ->assertJsonPath('data.color', GroupColor::Emerald->value)
        ->assertJsonPath('data.can_access_ranking', true)
        ->assertJsonPath('data.can_view_all_ranking_groups', true);

    $this->assertDatabaseHas('groups', [
        'name' => 'Juniors',
        'color' => GroupColor::Emerald->value,
        'can_access_ranking' => true,
        'can_view_all_ranking_groups' => true,
    ]);
});

test('admin can update a group', function () {
    $admin = User::factory()->create();
    attachAdminRoleForGroups($admin);
    $group = Group::factory()->create([
        'name' => 'Old name',
        'color' => GroupColor::Slate->value,
        'can_access_ranking' => false,
        'can_view_all_ranking_groups' => false,
    ]);

    $response = $this->actingAs($admin)->patch(route('groups.update', $group), [
        'name' => 'New name',
        'color' => GroupColor::Rose->value,
        'can_access_ranking' => true,
        'can_view_all_ranking_groups' => false,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.color', GroupColor::Rose->value)
        ->assertJsonPath('data.can_access_ranking', true);

    expect($group->fresh()->name)->toBe('New name');
});

test('admin can delete a group and detach members', function () {
    $admin = User::factory()->create();
    attachAdminRoleForGroups($admin);
    $group = Group::factory()->create();
    $member = User::factory()->create();
    $group->users()->attach($member->id);

    $response = $this->actingAs($admin)->delete(route('groups.destroy', $group));

    $response->assertNoContent();
    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    $this->assertDatabaseMissing('group_user', [
        'group_id' => $group->id,
        'user_id' => $member->id,
    ]);
});

test('non-admin cannot manage groups', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    $this->actingAs($user)->post(route('groups.store'), [
        'name' => 'Blocked',
        'color' => GroupColor::Blue->value,
        'can_access_ranking' => false,
        'can_view_all_ranking_groups' => false,
    ])->assertForbidden();

    $this->actingAs($user)->patch(route('groups.update', $group), [
        'name' => 'Blocked update',
        'color' => GroupColor::Blue->value,
        'can_access_ranking' => false,
        'can_view_all_ranking_groups' => false,
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('groups.destroy', $group))->assertForbidden();
});
