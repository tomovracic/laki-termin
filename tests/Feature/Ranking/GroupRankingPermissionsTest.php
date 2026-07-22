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

function attachAdminRoleForRanking(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function createRatedMatch(User $winner, User $loser): void
{
    PlayedMatch::factory()->create([
        'player_one_user_id' => $winner->id,
        'player_two_user_id' => $loser->id,
        'entered_by' => $winner->id,
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 3,
        'played_at' => Date::parse('2026-01-01 10:00:00'),
    ]);
}

test('user without ranking access cannot view ranking page', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create([
        'can_access_ranking' => false,
        'can_view_all_ranking_groups' => false,
    ]);
    $user->groups()->attach($group->id);

    $this->actingAs($user)
        ->get(route('dashboard.ranking'))
        ->assertForbidden();
});

test('user with ranking access sees only own group sections', function () {
    $viewer = User::factory()->create(['first_name' => 'Viewer']);
    $ownGroup = Group::factory()->withRankingAccess()->create(['name' => 'Own group']);
    $otherGroup = Group::factory()->withRankingAccess()->create(['name' => 'Other group']);
    $viewer->groups()->attach($ownGroup->id);

    $ownWinner = User::factory()->create(['first_name' => 'Ana']);
    $ownLoser = User::factory()->create(['first_name' => 'Bruno']);
    $ownGroup->users()->attach([$ownWinner->id, $ownLoser->id]);
    createRatedMatch($ownWinner, $ownLoser);

    $otherWinner = User::factory()->create(['first_name' => 'Ceco']);
    $otherLoser = User::factory()->create(['first_name' => 'Dino']);
    $otherGroup->users()->attach([$otherWinner->id, $otherLoser->id]);
    createRatedMatch($otherWinner, $otherLoser);

    $this->actingAs($viewer)
        ->get(route('dashboard.ranking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ranking')
            ->has('groups', 1)
            ->where('groups.0.id', $ownGroup->id)
            ->where('groups.0.name', 'Own group')
            ->has('groups.0.rankings', 2));
});

test('user with view all ranking groups sees every non-empty group', function () {
    $viewer = User::factory()->create();
    $ownGroup = Group::factory()->withViewAllRankingGroups()->create(['name' => 'Alpha']);
    $otherGroup = Group::factory()->withRankingAccess()->create(['name' => 'Beta']);
    $viewer->groups()->attach($ownGroup->id);

    $alphaWinner = User::factory()->create(['first_name' => 'Ana']);
    $alphaLoser = User::factory()->create(['first_name' => 'Bruno']);
    $ownGroup->users()->attach([$alphaWinner->id, $alphaLoser->id]);
    createRatedMatch($alphaWinner, $alphaLoser);

    $betaWinner = User::factory()->create(['first_name' => 'Ceco']);
    $betaLoser = User::factory()->create(['first_name' => 'Dino']);
    $otherGroup->users()->attach([$betaWinner->id, $betaLoser->id]);
    createRatedMatch($betaWinner, $betaLoser);

    $this->actingAs($viewer)
        ->get(route('dashboard.ranking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ranking')
            ->has('groups', 2)
            ->where('groups.0.name', 'Alpha')
            ->where('groups.1.name', 'Beta'));
});

test('multi group permission union grants access from any group', function () {
    $user = User::factory()->create();
    $noAccess = Group::factory()->create([
        'can_access_ranking' => false,
        'can_view_all_ranking_groups' => false,
    ]);
    $withAccess = Group::factory()->withRankingAccess()->create();
    $user->groups()->attach([$noAccess->id, $withAccess->id]);

    $resolver = app(UserGroupPermissionResolver::class);

    expect($resolver->canAccessRanking($user))->toBeTrue();
    expect($resolver->canViewAllRankingGroups($user))->toBeFalse();
});

test('multi group permission union grants view all from any group', function () {
    $user = User::factory()->create();
    $ownOnly = Group::factory()->withRankingAccess()->create();
    $viewAll = Group::factory()->withViewAllRankingGroups()->create();
    $user->groups()->attach([$ownOnly->id, $viewAll->id]);

    $resolver = app(UserGroupPermissionResolver::class);

    expect($resolver->canAccessRanking($user))->toBeTrue();
    expect($resolver->canViewAllRankingGroups($user))->toBeTrue();
});

test('admin bypasses ranking group restrictions', function () {
    $admin = User::factory()->create();
    attachAdminRoleForRanking($admin);

    $groupA = Group::factory()->create(['name' => 'Group A', 'can_access_ranking' => false]);
    $groupB = Group::factory()->create(['name' => 'Group B', 'can_access_ranking' => false]);

    $winner = User::factory()->create(['first_name' => 'Ana']);
    $loser = User::factory()->create(['first_name' => 'Bruno']);
    $groupA->users()->attach([$winner->id, $loser->id]);
    createRatedMatch($winner, $loser);

    $resolver = app(UserGroupPermissionResolver::class);
    expect($resolver->canAccessRanking($admin))->toBeTrue();
    expect($resolver->canViewAllRankingGroups($admin))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard.ranking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ranking')
            ->has('groups', 1)
            ->where('groups.0.id', $groupA->id));

    expect($groupB->users()->count())->toBe(0);
});

test('shared auth exposes ranking permission flags', function () {
    $user = User::factory()->create();
    $group = Group::factory()->withViewAllRankingGroups()->create();
    $user->groups()->attach($group->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canAccessMatchHistory', false)
            ->where('auth.canViewAllMatchHistoryGroups', false)
            ->where('auth.canAccessRanking', true)
            ->where('auth.canViewAllRankingGroups', true));
});

test('admin without groups can access match history', function () {
    $admin = User::factory()->create();
    attachAdminRoleForRanking($admin);

    $resolver = app(UserGroupPermissionResolver::class);
    expect($resolver->canAccessMatchHistory($admin))->toBeTrue();
    expect($resolver->canViewAllMatchHistoryGroups($admin))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard.match-history'))
        ->assertOk();
});
