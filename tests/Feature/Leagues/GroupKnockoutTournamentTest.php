<?php

use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
use App\DTO\Leagues\LeagueParticipantInputData;
use App\Enums\LeagueFormat;
use App\Enums\LeagueMatchStatus;
use App\Enums\LeagueStage;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\Role;
use App\Models\User;
use App\Services\Leagues\GroupQualificationService;

function assignGroupKnockoutAdmin(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function groupKnockoutParticipants(array $users): array
{
    return [
        new LeagueParticipantInputData($users[0]->id, null, null),
        new LeagueParticipantInputData($users[1]->id, null, null),
        new LeagueParticipantInputData(null, 'Gost', 'Ana'),
        new LeagueParticipantInputData($users[2]->id, null, null),
        new LeagueParticipantInputData($users[3]->id, null, null),
        new LeagueParticipantInputData(null, 'Gost', 'Bruno'),
        new LeagueParticipantInputData($users[4]->id, null, null),
        new LeagueParticipantInputData($users[5]->id, null, null),
        new LeagueParticipantInputData(null, 'Gost', 'Ciro'),
    ];
}

function threeGroupPayload(): array
{
    return [
        new LeagueGroupInputData('A', [0, 1, 2]),
        new LeagueGroupInputData('B', [3, 4, 5]),
        new LeagueGroupInputData('C', [6, 7, 8]),
    ];
}

function findParticipantMatch(League $league, int $firstId, int $secondId): LeagueMatch
{
    $match = $league->matches()
        ->where(function ($query) use ($firstId, $secondId): void {
            $query->where(function ($inner) use ($firstId, $secondId): void {
                $inner->where('player_one_participant_id', $firstId)
                    ->where('player_two_participant_id', $secondId);
            })->orWhere(function ($inner) use ($firstId, $secondId): void {
                $inner->where('player_one_participant_id', $secondId)
                    ->where('player_two_participant_id', $firstId);
            });
        })
        ->first();

    expect($match)->not->toBeNull();

    return $match;
}

function recordParticipantWin(
    User $admin,
    LeagueMatch $match,
    int $winnerParticipantId,
    int $winnerGames = 6,
    int $loserGames = 4,
): void {
    $winnerIsPlayerOne = $match->player_one_participant_id === $winnerParticipantId;

    test()->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $match->league_id, 'match' => $match]),
        $winnerIsPlayerOne
            ? [
                'set1_player_one_games' => $winnerGames,
                'set1_player_two_games' => $loserGames,
                'set2_player_one_games' => $winnerGames,
                'set2_player_two_games' => $loserGames,
            ]
            : [
                'set1_player_one_games' => $loserGames,
                'set1_player_two_games' => $winnerGames,
                'set2_player_one_games' => $loserGames,
                'set2_player_two_games' => $winnerGames,
            ],
    )->assertOk();
}

function playStandardGroup(
    User $admin,
    League $league,
    int $firstId,
    int $secondId,
    int $thirdId,
    int $secondVsThirdWinnerGames = 6,
    int $secondVsThirdLoserGames = 4,
): void {
    recordParticipantWin($admin, findParticipantMatch($league, $firstId, $secondId), $firstId);
    recordParticipantWin($admin, findParticipantMatch($league, $firstId, $thirdId), $firstId);
    recordParticipantWin(
        $admin,
        findParticipantMatch($league, $secondId, $thirdId),
        $secondId,
        $secondVsThirdWinnerGames,
        $secondVsThirdLoserGames,
    );
}

test('admin can create 3x3 group knockout with guests', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Grupni kup',
        'format' => 'group_knockout',
        'sets_best_of' => 3,
        'qualify_per_group' => 1,
        'best_runners_up' => 1,
        'participants' => [
            ['user_id' => $users[0]->id],
            ['user_id' => $users[1]->id],
            ['first_name' => 'Gost', 'last_name' => 'Ana'],
            ['user_id' => $users[2]->id],
            ['user_id' => $users[3]->id],
            ['first_name' => 'Gost', 'last_name' => 'Bruno'],
            ['user_id' => $users[4]->id],
            ['user_id' => $users[5]->id],
            ['first_name' => 'Gost', 'last_name' => 'Ciro'],
        ],
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1, 2]],
            ['name' => 'B', 'participant_indexes' => [3, 4, 5]],
            ['name' => 'C', 'participant_indexes' => [6, 7, 8]],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Grupni kup')->first();

    expect($league)->not->toBeNull();
    expect($league->format)->toBe(LeagueFormat::GroupKnockout);
    expect($league->current_stage)->toBe(LeagueStage::Group);
    expect($league->qualify_per_group)->toBe(1);
    expect($league->best_runners_up)->toBe(1);
    expect($league->participants()->count())->toBe(9);
    expect($league->participants()->whereNull('user_id')->count())->toBe(3);
    expect($league->groups()->count())->toBe(3);
    expect($league->matches()->count())->toBe(9);
    expect($league->matches()->whereNotNull('league_group_id')->count())->toBe(9);
    expect($league->matches()->whereNotNull('bracket_round')->count())->toBe(0);
});

test('admin can create group knockout with two groups of four', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(8)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Dvije skupine',
        'format' => 'group_knockout',
        'sets_best_of' => 3,
        'qualify_per_group' => 1,
        'best_runners_up' => 0,
        'participants' => $users->map(fn (User $user) => ['user_id' => $user->id])->all(),
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1, 2, 3]],
            ['name' => 'B', 'participant_indexes' => [4, 5, 6, 7]],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Dvije skupine')->first();

    expect($league)->not->toBeNull();
    expect($league->groups()->count())->toBe(2);
    expect($league->participants()->count())->toBe(8);
    expect($league->matches()->whereNotNull('league_group_id')->count())->toBe(12);
    expect($league->matches()->whereNotNull('bracket_round')->count())->toBe(0);
});

test('admin can create group knockout with four groups of two', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(8)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Cetiri skupine',
        'format' => 'group_knockout',
        'sets_best_of' => 3,
        'qualify_per_group' => 1,
        'best_runners_up' => 0,
        'participants' => $users->map(fn (User $user) => ['user_id' => $user->id])->all(),
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1]],
            ['name' => 'B', 'participant_indexes' => [2, 3]],
            ['name' => 'C', 'participant_indexes' => [4, 5]],
            ['name' => 'D', 'participant_indexes' => [6, 7]],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Cetiri skupine')->first();

    expect($league)->not->toBeNull();
    expect($league->groups()->count())->toBe(4);
    expect($league->participants()->count())->toBe(8);
    expect($league->matches()->whereNotNull('league_group_id')->count())->toBe(4);
    expect($league->matches()->whereNotNull('bracket_round')->count())->toBe(0);
});

test('cannot start knockout before group matches are finished', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Rani knockout',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::GroupKnockout,
        setsBestOf: 3,
        participants: groupKnockoutParticipants($users->all()),
        qualifyPerGroup: 1,
        bestRunnersUp: 1,
        groups: threeGroupPayload(),
    ));

    $this->actingAs($admin)
        ->postJson(route('leagues.knockout.start', $league))
        ->assertUnprocessable();

    expect($league->fresh()->current_stage)->toBe(LeagueStage::Group);
    expect($league->matches()->whereNotNull('bracket_round')->count())->toBe(0);
});

test('first from each group and best second advance to knockout', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Kvalifikacije',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::GroupKnockout,
        setsBestOf: 3,
        participants: groupKnockoutParticipants($users->all()),
        qualifyPerGroup: 1,
        bestRunnersUp: 1,
        groups: threeGroupPayload(),
    ));

    $participants = $league->participants()->orderBy('id')->get();
    $ids = $participants->pluck('id')->all();

    playStandardGroup($admin, $league, $ids[0], $ids[1], $ids[2]);
    playStandardGroup($admin, $league, $ids[3], $ids[4], $ids[5]);
    playStandardGroup($admin, $league, $ids[6], $ids[7], $ids[8], 6, 0);

    $qualifiers = app(GroupQualificationService::class)->qualify($league->fresh(['groups.participants.user', 'groups.participants.group', 'matches']));
    $qualifierIds = array_map(fn ($entry) => $entry->participantId, $qualifiers);

    expect($qualifierIds)->toHaveCount(4);
    expect($qualifierIds)->toContain($ids[0], $ids[3], $ids[6], $ids[7]);
    expect($qualifiers[3]->participantId)->toBe($ids[7]);

    $this->actingAs($admin)
        ->postJson(route('leagues.knockout.start', $league))
        ->assertSuccessful();

    $league = $league->fresh();

    expect($league->current_stage)->toBe(LeagueStage::Knockout);
    expect($league->matches()->where('bracket_round', 1)->where('is_bye', false)->count())->toBe(2);

    $semiMatches = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->get();

    foreach ($semiMatches as $match) {
        recordParticipantWin($admin, $match, (int) $match->player_one_participant_id);
    }

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertSuccessful();

    expect($league->matches()->where('bracket_round', 2)->count())->toBe(1);
});

test('group knockout rejects too many best runners up', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();

    $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Lose pravilo',
        'format' => 'group_knockout',
        'sets_best_of' => 3,
        'qualify_per_group' => 1,
        'best_runners_up' => 4,
        'participants' => [
            ['user_id' => $users[0]->id],
            ['user_id' => $users[1]->id],
            ['first_name' => 'Gost', 'last_name' => 'Ana'],
            ['user_id' => $users[2]->id],
            ['user_id' => $users[3]->id],
            ['first_name' => 'Gost', 'last_name' => 'Bruno'],
            ['user_id' => $users[4]->id],
            ['user_id' => $users[5]->id],
            ['first_name' => 'Gost', 'last_name' => 'Ciro'],
        ],
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1, 2]],
            ['name' => 'B', 'participant_indexes' => [3, 4, 5]],
            ['name' => 'C', 'participant_indexes' => [6, 7, 8]],
        ],
    ])->assertUnprocessable();
});

test('group knockout rejects undersized groups', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(4)->create();

    $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Mala skupina',
        'format' => 'group_knockout',
        'sets_best_of' => 3,
        'qualify_per_group' => 1,
        'best_runners_up' => 1,
        'participants' => [
            ['user_id' => $users[0]->id],
            ['user_id' => $users[1]->id],
            ['user_id' => $users[2]->id],
            ['user_id' => $users[3]->id],
        ],
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1, 2]],
            ['name' => 'B', 'participant_indexes' => [3]],
        ],
    ])->assertUnprocessable();
});

test('cannot add participants to group knockout after creation', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();
    $extra = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Zatvoreni grupni',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::GroupKnockout,
        setsBestOf: 3,
        participants: groupKnockoutParticipants($users->all()),
        qualifyPerGroup: 1,
        bestRunnersUp: 1,
        groups: threeGroupPayload(),
    ));

    $this->actingAs($admin)
        ->postJson(route('leagues.participants.store', $league), [
            'user_id' => $extra->id,
        ])
        ->assertUnprocessable();
});

test('guest can win a knockout tournament', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $player = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Gost prvak',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        participants: [
            new LeagueParticipantInputData($player->id, null, null),
            new LeagueParticipantInputData(null, 'Gost', 'Prvak'),
        ],
    ));

    $match = $league->matches()->first();
    $guest = $league->participants()->whereNull('user_id')->first();

    recordParticipantWin($admin, $match, $guest->id);

    $champion = app(\App\Services\Leagues\KnockoutBracketGeneratorService::class)
        ->resolveChampion($league->fresh(['matches.playerOne', 'matches.playerTwo', 'participants.user']));

    expect($champion)->not->toBeNull();
    expect($champion['user_id'])->toBeNull();
    expect($champion['name'])->toBe('Gost Prvak');
    expect($league->matches()->where('status', LeagueMatchStatus::Played->value)->count())->toBe(1);
});

test('admin can create group knockout doubles with mixed guest pairs', function () {
    $admin = User::factory()->create();
    assignGroupKnockoutAdmin($admin);
    $users = User::factory()->count(6)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Grupni parovi',
        'format' => 'group_knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 1,
        'qualify_per_group' => 1,
        'best_runners_up' => 0,
        'pairs' => [
            [$users[0]->id, $users[1]->id],
            [$users[2]->id, $users[3]->id],
            [
                'player_one' => ['user_id' => $users[4]->id],
                'player_two' => ['first_name' => 'Gost', 'last_name' => 'A'],
            ],
            [
                'player_one' => ['first_name' => 'Gost', 'last_name' => 'B'],
                'player_two' => ['user_id' => $users[5]->id],
            ],
        ],
        'groups' => [
            ['name' => 'A', 'participant_indexes' => [0, 1]],
            ['name' => 'B', 'participant_indexes' => [2, 3]],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Grupni parovi')->first();

    expect($league)->not->toBeNull();
    expect($league->participant_mode->value)->toBe('doubles');
    expect($league->participants()->count())->toBe(4);
    expect($league->groups()->count())->toBe(2);
    expect($league->matches()->whereNotNull('league_group_id')->count())->toBe(2);

    $match = $league->matches()->first();
    expect($match->playerOneDisplayName())->toContain(' / ');
});
