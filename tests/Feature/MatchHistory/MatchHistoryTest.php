<?php

use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueMatchStatus;
use App\Models\LeagueMatch;
use App\Models\PlayedMatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function assignLeagueAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
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

test('authenticated user can view empty match history page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.match-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/match-history')
            ->has('matches', 0));
});

test('user search returns matching users by name', function () {
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
});

test('user can create casual match with guest opponent', function () {
    $user = User::factory()->create();

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
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('played-matches.store'), validPlayedMatchPayload([
        'player_two' => [
            'user_id' => $user->id,
        ],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['player_two']);
});

test('invalid match result is rejected for casual matches', function () {
    $user = User::factory()->create();
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

    $player = User::factory()->create();
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

    $player = User::factory()->create();
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
    $user = User::factory()->create();
    $opponent = User::factory()->create();

    $playedMatch = PlayedMatch::factory()->create([
        'player_one_user_id' => $user->id,
        'player_two_user_id' => $opponent->id,
        'entered_by' => $user->id,
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
    ])->assertOk()
        ->assertJsonPath('data.set1_player_one_games', 7)
        ->assertJsonPath('data.set1_player_two_games', 5);

    $playedMatch->refresh();
    expect($playedMatch->set1_player_one_games)->toBe(7);
    expect($playedMatch->set1_player_two_games)->toBe(5);
});

test('registered opponent can update casual match scores', function () {
    $user = User::factory()->create();
    $opponent = User::factory()->create();

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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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
