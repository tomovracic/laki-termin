<?php

use App\Models\Group;
use App\Models\PlayedMatch;
use App\Models\Role;
use App\Models\User;
use App\Services\Groups\UserGroupPermissionResolver;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function attachAdminRoleForMatchHistory(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function createPublicGroupMatch(User $playerOne, User $playerTwo): PlayedMatch
{
    return PlayedMatch::factory()->create([
        'player_one_user_id' => $playerOne->id,
        'player_two_user_id' => $playerTwo->id,
        'entered_by' => $playerOne->id,
        'is_public' => true,
        'played_at' => Date::parse('2026-01-01 10:00:00'),
    ]);
}

test('user without match history access cannot view match history page', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create([
        'can_access_match_history' => false,
        'can_view_all_match_history_groups' => false,
    ]);
    $user->groups()->attach($group->id);

    $this->actingAs($user)
        ->get(route('dashboard.match-history'))
        ->assertForbidden();
});

test('user with match history access sees only own group matches', function () {
    $viewer = User::factory()->create();
    $ownGroup = Group::factory()->withMatchHistoryAccess()->create(['name' => 'Own group']);
    $otherGroup = Group::factory()->withMatchHistoryAccess()->create(['name' => 'Other group']);
    $viewer->groups()->attach($ownGroup->id);

    $ownPlayerOne = User::factory()->create();
    $ownPlayerTwo = User::factory()->create();
    $ownGroup->users()->attach([$ownPlayerOne->id, $ownPlayerTwo->id]);
    $ownMatch = createPublicGroupMatch($ownPlayerOne, $ownPlayerTwo);

    $otherPlayerOne = User::factory()->create();
    $otherPlayerTwo = User::factory()->create();
    $otherGroup->users()->attach([$otherPlayerOne->id, $otherPlayerTwo->id]);
    createPublicGroupMatch($otherPlayerOne, $otherPlayerTwo);

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 1)
            ->where('matches.0.id', "casual-{$ownMatch->id}"));
});

test('user with view all match history groups sees matches from every group', function () {
    $viewer = User::factory()->create();
    $ownGroup = Group::factory()->withViewAllMatchHistoryGroups()->create(['name' => 'Alpha']);
    $otherGroup = Group::factory()->withMatchHistoryAccess()->create(['name' => 'Beta']);
    $viewer->groups()->attach($ownGroup->id);

    $alphaOne = User::factory()->create();
    $alphaTwo = User::factory()->create();
    $ownGroup->users()->attach([$alphaOne->id, $alphaTwo->id]);
    createPublicGroupMatch($alphaOne, $alphaTwo);

    $betaOne = User::factory()->create();
    $betaTwo = User::factory()->create();
    $otherGroup->users()->attach([$betaOne->id, $betaTwo->id]);
    createPublicGroupMatch($betaOne, $betaTwo);

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 2));
});

test('multi group permission union grants match history access from any group', function () {
    $user = User::factory()->create();
    $noAccess = Group::factory()->create([
        'can_access_match_history' => false,
        'can_view_all_match_history_groups' => false,
    ]);
    $withAccess = Group::factory()->withMatchHistoryAccess()->create();
    $user->groups()->attach([$noAccess->id, $withAccess->id]);

    $resolver = app(UserGroupPermissionResolver::class);

    expect($resolver->canAccessMatchHistory($user))->toBeTrue();
    expect($resolver->canViewAllMatchHistoryGroups($user))->toBeFalse();
});

test('multi group permission union grants view all match history from any group', function () {
    $user = User::factory()->create();
    $ownOnly = Group::factory()->withMatchHistoryAccess()->create();
    $viewAll = Group::factory()->withViewAllMatchHistoryGroups()->create();
    $user->groups()->attach([$ownOnly->id, $viewAll->id]);

    $resolver = app(UserGroupPermissionResolver::class);

    expect($resolver->canAccessMatchHistory($user))->toBeTrue();
    expect($resolver->canViewAllMatchHistoryGroups($user))->toBeTrue();
});

test('admin bypasses match history group restrictions', function () {
    $admin = User::factory()->create();
    attachAdminRoleForMatchHistory($admin);

    $groupA = Group::factory()->create([
        'name' => 'Group A',
        'can_access_match_history' => false,
    ]);
    $groupB = Group::factory()->create([
        'name' => 'Group B',
        'can_access_match_history' => false,
    ]);

    $playerOne = User::factory()->create();
    $playerTwo = User::factory()->create();
    $groupA->users()->attach([$playerOne->id, $playerTwo->id]);
    createPublicGroupMatch($playerOne, $playerTwo);

    $resolver = app(UserGroupPermissionResolver::class);
    expect($resolver->canAccessMatchHistory($admin))->toBeTrue();
    expect($resolver->canViewAllMatchHistoryGroups($admin))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 1));

    expect($groupB->users()->count())->toBe(0);
});

test('shared auth exposes match history permission flags', function () {
    $user = User::factory()->create();
    $group = Group::factory()->withViewAllMatchHistoryGroups()->create();
    $user->groups()->attach($group->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canAccessMatchHistory', true)
            ->where('auth.canViewAllMatchHistoryGroups', true));
});

test('own private match remains visible without view all permission', function () {
    $viewer = User::factory()->create();
    $group = Group::factory()->withMatchHistoryAccess()->create();
    $viewer->groups()->attach($group->id);
    $opponent = User::factory()->create();

    $privateMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $viewer->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $viewer->id,
        'is_public' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('matches', 1)
            ->where('matches.0.id', "casual-{$privateMatch->id}"));
});
