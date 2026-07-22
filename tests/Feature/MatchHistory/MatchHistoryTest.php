<?php

use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueMatchStatus;
use App\Models\Group;
use App\Models\LeagueMatch;
use App\Models\PlayedMatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->matchHistoryGroup = Group::factory()->withMatchHistoryAccess()->create();
});

function assignLeagueAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function createGroupedUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->groups()->attach(test()->matchHistoryGroup->id);

    return $user;
}

function validPlayedMatchPayload(array $overrides = []): array
{
    return array_merge([
        'played_at' => Date::now()->toIso8601String(),
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 3,
    ], $overrides);
}

test('authenticated grouped user can view empty match history page', function () {
    $user = createGroupedUser();

    $this->actingAs($user)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 0));
});

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

test('user without match history access cannot create casual match', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create([
        'can_access_match_history' => false,
    ]);
    $user->groups()->attach($group->id);
    $opponent = User::factory()->create();

    $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $opponent->id,
        ],
    ]))->assertForbidden();
});

test('shared auth exposes match history access for users with permission', function () {
    $user = createGroupedUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canAccessMatchHistory', true)
            ->where('auth.canViewAllMatchHistoryGroups', false));
});

test('shared auth hides match history access for users without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canAccessMatchHistory', false)
            ->where('auth.canViewAllMatchHistoryGroups', false));
});

test('user search returns matching users by name', function () {
    $user = createGroupedUser();
    $match = User::factory()->create([
        'first_name' => 'Ivan',
        'last_name' => 'Horvat',
        'email' => 'ivan.horvat@example.com',
    ]);
    User::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Anic',
    ]);

    $response = $this->actingAs($user)->getJson(route('users.search', ['q' => 'Ivan Hor']));

    $response->assertOk()
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.name', 'Ivan Horvat');
});

test('user can create casual match with registered opponent', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $opponent->id,
        ],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.player_two.user_id', $opponent->id);

    $playedMatch = PlayedMatch::query()->first();
    expect($playedMatch)->not->toBeNull();
    expect($playedMatch->player_one_user_id)->toBe($user->id);
    expect($playedMatch->player_two_user_id)->toBe($opponent->id);
    expect($playedMatch->entered_by)->toBe($user->id);
    expect($playedMatch->is_public)->toBeTrue();
    expect($playedMatch->is_ranked)->toBeTrue();
});

test('user can create casual match with visibility and ranked flags', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $opponent->id,
        ],
        'is_public' => false,
        'is_ranked' => false,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.is_public', false)
        ->assertJsonPath('data.is_ranked', false);

    $playedMatch = PlayedMatch::query()->first();
    expect($playedMatch)->not->toBeNull();
    expect($playedMatch->is_public)->toBeFalse();
    expect($playedMatch->is_ranked)->toBeFalse();
});

test('user can create casual match with guest opponent', function () {
    $user = createGroupedUser();

    $response = $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'first_name' => 'Marko',
            'last_name' => 'Markovic',
        ],
    ]));

    $response->assertCreated();

    $playedMatch = PlayedMatch::query()->first();
    expect($playedMatch)->not->toBeNull();
    expect($playedMatch->player_two_user_id)->toBeNull();
    expect($playedMatch->player_two_first_name)->toBe('Marko');
    expect($playedMatch->player_two_last_name)->toBe('Markovic');
});

test('creating match with self as opponent is rejected', function () {
    $user = createGroupedUser();

    $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $user->id,
        ],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['player_two']);
});

test('invalid match result is rejected for casual matches', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $opponent->id,
        ],
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 6,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 4,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['result']);
});

test('match history includes played league matches for participant', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $player = createGroupedUser();
    $opponent = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Test liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [$player->id, $opponent->id],
    ));

    /** @var LeagueMatch $match */
    $match = $league->matches()->first();
    $match->forceFill([
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 2,
        'status' => LeagueMatchStatus::Played->value,
        'played_at' => Date::now(),
        'entered_by' => $admin->id,
    ])->save();

    $this->actingAs($player)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 1)
            ->where('matches.0.id', "league-{$match->id}")
            ->where('matches.0.source', 'league')
            ->where('matches.0.league.id', $league->id));
});

test('match history merges casual and league matches', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $player = createGroupedUser();
    $leagueOpponent = User::factory()->create();
    $casualOpponent = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Kombinirana liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [$player->id, $leagueOpponent->id],
    ));

    /** @var LeagueMatch $leagueMatch */
    $leagueMatch = $league->matches()->first();
    $leagueMatch->forceFill([
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 2,
        'status' => LeagueMatchStatus::Played->value,
        'played_at' => Date::now()->subDay(),
        'entered_by' => $admin->id,
    ])->save();

    PlayedMatch::factory()->create([
        'player_one_user_id' => $player->id,
        'player_two_user_id' => $casualOpponent->id,
        'entered_by' => $player->id,
        'played_at' => Date::now(),
    ]);

    $this->actingAs($player)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('matches', 2)
            ->where('matches.0.source', 'casual')
            ->where('matches.1.source', 'league'));
});

test('guest user cannot access match history', function () {
    $this->get(route('dashboard.match-history'))->assertRedirect(route('login'));
});

test('user can update casual match scores', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
        'is_public' => true,
        'is_ranked' => true,
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 3,
    ]);

    $this->actingAs($user)->patchJson(route('played-matches.update', $playedMatch), [
        'set1_player_one_games' => 7,
        'set1_player_two_games' => 5,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 4,
        'is_public' => false,
        'is_ranked' => false,
    ])->assertOk()
        ->assertJsonPath('data.set1_player_one_games', 7)
        ->assertJsonPath('data.set1_player_two_games', 5)
        ->assertJsonPath('data.is_public', false)
        ->assertJsonPath('data.is_ranked', false);

    $playedMatch->refresh();
    expect($playedMatch->set1_player_one_games)->toBe(7);
    expect($playedMatch->set1_player_two_games)->toBe(5);
    expect($playedMatch->is_public)->toBeFalse();
    expect($playedMatch->is_ranked)->toBeFalse();
});

test('registered opponent can update casual match scores', function () {
    $user = createGroupedUser();
    $opponent = createGroupedUser();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
    ]);

    $this->actingAs($opponent)->patchJson(route('played-matches.update', $playedMatch), [
        'set1_player_one_games' => 4,
        'set1_player_two_games' => 6,
        'set2_player_one_games' => 3,
        'set2_player_two_games' => 6,
    ])->assertOk();
});

test('user cannot update casual match they are not part of', function () {
    $user = createGroupedUser();
    $otherUser = User::factory()->create();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $otherUser->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $otherUser->id,
    ]);

    $this->actingAs($user)->patchJson(route('played-matches.update', $playedMatch), [
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 3,
    ])->assertForbidden();
});

test('invalid score update is rejected for casual matches', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
    ]);

    $this->actingAs($user)->patchJson(route('played-matches.update', $playedMatch), [
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 6,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 4,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['result']);
});

test('user can delete casual match', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
    ]);

    $this->actingAs($user)->deleteJson(route('played-matches.destroy', $playedMatch))
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect(PlayedMatch::query()->find($playedMatch->id))->toBeNull();
});

test('user cannot delete casual match they are not part of', function () {
    $user = createGroupedUser();
    $otherUser = User::factory()->create();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $otherUser->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $otherUser->id,
    ]);

    $this->actingAs($user)->deleteJson(route('played-matches.destroy', $playedMatch))
        ->assertForbidden();
});

test('match history exposes edit and delete flags for casual matches', function () {
    $user = createGroupedUser();
    $opponent = User::factory()->create();

    PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('matches.0.can_edit', true)
            ->where('matches.0.can_delete', true));
});

test('match history includes other users casual matches from own group', function () {
    $viewer = createGroupedUser();
    $playerOne = User::factory()->create();
    $playerTwo = User::factory()->create();
    test()->matchHistoryGroup->users()->attach([$playerOne->id, $playerTwo->id]);

    $otherMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $playerOne->id,
        'player_two_user_id' => $playerTwo->id,
        'entered_by' => $playerOne->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('matches', 1)
            ->where('matches.0.id', "casual-{$otherMatch->id}")
            ->where('matches.0.can_edit', false)
            ->where('matches.0.can_delete', false));
});

test('private casual match is hidden from users outside the match', function () {
    $viewer = createGroupedUser();
    $playerOne = User::factory()->create();
    $playerTwo = User::factory()->create();

    PlayedMatch::factory()->create([
        'player_one_user_id' => $playerOne->id,
        'player_two_user_id' => $playerTwo->id,
        'entered_by' => $playerOne->id,
        'is_public' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('matches', 0));
});

test('private casual match is visible to participants', function () {
    $viewer = createGroupedUser();
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

test('match history includes played league matches from own group user is not part of', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $viewer = createGroupedUser();
    $playerOne = User::factory()->create();
    $playerTwo = User::factory()->create();
    test()->matchHistoryGroup->users()->attach([$playerOne->id, $playerTwo->id]);

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Javna liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [$playerOne->id, $playerTwo->id],
    ));

    /** @var LeagueMatch $match */
    $match = $league->matches()->first();
    $match->forceFill([
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 2,
        'status' => LeagueMatchStatus::Played->value,
        'played_at' => Date::now(),
        'entered_by' => $admin->id,
    ])->save();

    $this->actingAs($viewer)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('matches', 1)
            ->where('matches.0.id', "league-{$match->id}")
            ->where('matches.0.can_edit', false)
            ->where('matches.0.can_delete', false));
});
