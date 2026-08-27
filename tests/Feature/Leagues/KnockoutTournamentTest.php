<?php

use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueMatchStatus;
use App\Enums\LeagueParticipantMode;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;
use App\Models\Role;
use App\Models\User;
use App\Services\Leagues\KnockoutBracketGeneratorService;

function assignTournamentAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function recordBestOfOneWin(LeagueMatch $match, User $admin, int $playerOneGames = 6, int $playerTwoGames = 4): void
{
    test()->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $match->league_id, 'match' => $match]),
        [
            'set1_player_one_games' => $playerOneGames,
            'set1_player_two_games' => $playerTwoGames,
        ],
    )->assertOk();
}

test('admin can create knockout tournament with one bye for five players', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(5)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Knockout kup',
        'format' => 'knockout',
        'sets_best_of' => 3,
        'knockout_draw_mode' => 'seeded',
        'participant_ids' => $players->pluck('id')->all(),
    ]);

    $response->assertSuccessful();

    $league = League::query()->first();

    expect($league)->not->toBeNull();
    expect($league->format)->toBe(LeagueFormat::Knockout);
    expect($league->sets_best_of)->toBe(3);
    expect($league->knockout_draw_mode)->toBe(KnockoutDrawMode::Seeded);
    expect($league->participants()->count())->toBe(5);

    // Only first round: 2 matches + 1 bye; later rounds not generated yet.
    expect($league->matches()->count())->toBe(3);
    expect($league->matches()->where('bracket_round', 1)->count())->toBe(3);
    expect($league->matches()->where('is_bye', true)->where('bracket_round', 1)->count())->toBe(1);
    expect($league->matches()->where('bracket_round', '>', 1)->count())->toBe(0);

    expect(
        $league->matches()
            ->where('bracket_round', 1)
            ->where('is_bye', false)
            ->where('status', LeagueMatchStatus::Pending->value)
            ->count()
    )->toBe(2);

    $byeMatch = $league->matches()->where('is_bye', true)->first();
    $seedOne = $league->participants()->where('seed', 1)->first();

    expect($byeMatch->player_one_id)->toBe($seedOne->user_id);
    expect($seedOne->fresh()->received_bye)->toBeTrue();
});

test('knockout with six players has three first-round matches and no byes', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(6)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Sest igraca',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    expect($league->matches()->where('is_bye', true)->count())->toBe(0);
    expect($league->matches()->where('bracket_round', 1)->count())->toBe(3);
    expect($league->matches()->where('bracket_round', '>', 1)->count())->toBe(0);
    expect(
        $league->matches()
            ->where('bracket_round', 1)
            ->where('is_bye', false)
            ->where('status', LeagueMatchStatus::Pending->value)
            ->count()
    )->toBe(3);
});

test('knockout with ten players has five first-round matches and zero byes', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(10)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Deset igraca',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    expect($league->matches()->where('bracket_round', 1)->count())->toBe(5);
    expect($league->matches()->where('is_bye', true)->count())->toBe(0);
    expect($league->matches()->where('bracket_round', '>', 1)->count())->toBe(0);
});

test('knockout with eleven players has five matches and one bye in round one', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(11)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Jedanaest igraca',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    expect($league->matches()->where('bracket_round', 1)->count())->toBe(6);
    expect($league->matches()->where('is_bye', true)->count())->toBe(1);
    expect(
        $league->matches()
            ->where('is_bye', false)
            ->where('status', LeagueMatchStatus::Pending->value)
            ->count()
    )->toBe(5);
    expect($league->matches()->where('bracket_round', '>', 1)->count())->toBe(0);
});

test('recording results does not generate the next round until admin finishes the round', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Cetiri igraca',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    expect($league->matches()->where('bracket_round', 1)->count())->toBe(2);
    expect($league->matches()->where('bracket_round', 2)->count())->toBe(0);

    $roundOne = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->get();

    recordBestOfOneWin($roundOne[0], $admin);
    expect(LeagueMatch::query()->where('league_id', $league->id)->where('bracket_round', 2)->count())->toBe(0);

    recordBestOfOneWin($roundOne[1], $admin);
    expect(LeagueMatch::query()->where('league_id', $league->id)->where('bracket_round', 2)->count())->toBe(0);

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertOk();

    expect(LeagueMatch::query()->where('league_id', $league->id)->where('bracket_round', 2)->count())->toBe(1);

    $final = LeagueMatch::query()->where('league_id', $league->id)->where('bracket_round', 2)->first();
    expect($final->player_one_id)->not->toBeNull();
    expect($final->player_two_id)->not->toBeNull();
});

test('admin can edit knockout result before finishing the round', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Edit rezultat',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $match = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->first();

    recordBestOfOneWin($match, $admin, 6, 4);
    $match->refresh();
    expect($match->set1_player_one_games)->toBe(6);
    expect($match->set1_player_two_games)->toBe(4);

    recordBestOfOneWin($match, $admin, 4, 6);
    $match->refresh();
    expect($match->set1_player_one_games)->toBe(4);
    expect($match->set1_player_two_games)->toBe(6);
});

test('cannot edit knockout result after the round is finished', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Zakljucano kolo',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $roundOne = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->get();

    foreach ($roundOne as $match) {
        recordBestOfOneWin($match, $admin);
    }

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertOk();

    $this->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $roundOne[0]]),
        [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 0,
        ],
    )->assertUnprocessable();
});

test('cannot finish an incomplete knockout round', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Nepotpuno kolo',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $first = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->first();

    recordBestOfOneWin($first, $admin);

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertUnprocessable();
});

test('seeded bye prefers best seed who has not received a bye', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(5)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Bye preference',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $seedOne = $league->participants()->where('seed', 1)->first();
    expect($seedOne->received_bye)->toBeTrue();

    $pending = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->get();

    foreach ($pending as $match) {
        recordBestOfOneWin($match, $admin);
    }

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertOk();

    $roundTwo = LeagueMatch::query()
        ->where('league_id', $league->id)
        ->where('bracket_round', 2)
        ->get();

    // 3 advancers → final three RR, no bye
    expect($roundTwo)->toHaveCount(3);
    expect($roundTwo->where('is_bye', true))->toHaveCount(0);
});

test('three players start with round-robin and produce a champion', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Mini kup',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    expect($league->matches()->count())->toBe(3);
    expect($league->matches()->where('is_bye', true)->count())->toBe(0);

    $matches = $league->matches()->orderBy('bracket_position')->get();

    // Make player[0] win both of their matches → champion
    foreach ($matches as $match) {
        $p1IsFirst = $match->player_one_id === $players[0]->id;
        $p2IsFirst = $match->player_two_id === $players[0]->id;

        if ($p1IsFirst) {
            recordBestOfOneWin($match, $admin, 6, 4);
        } elseif ($p2IsFirst) {
            recordBestOfOneWin($match, $admin, 4, 6);
        } else {
            recordBestOfOneWin($match, $admin, 6, 4);
        }
    }

    $champion = app(KnockoutBracketGeneratorService::class)->resolveChampion($league->fresh(['matches.playerOne', 'matches.playerTwo', 'participants.user']));

    expect($champion)->not->toBeNull();
    expect($champion['user_id'])->toBe($players[0]->id);
    expect(LeagueMatch::query()->where('league_id', $league->id)->where('bracket_round', 2)->count())->toBe(0);
});

test('final three with equal wins uses set then game difference for champion', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Finalna trojka tiebreak',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $a = $players[0];
    $b = $players[1];
    $c = $players[2];

    $matches = $league->matches()->orderBy('bracket_position')->get();

    foreach ($matches as $match) {
        $ids = [$match->player_one_id, $match->player_two_id];

        // Circular: A>B, B>C, C>A — each has 1 win.
        // Set scores: A beats B 2-0, B beats C 2-0, C beats A 2-1
        // → A set diff +1, B 0, C -1 → A champion.
        if (in_array($a->id, $ids, true) && in_array($b->id, $ids, true)) {
            $aIsOne = $match->player_one_id === $a->id;
            $this->actingAs($admin)->patchJson(
                route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
                $aIsOne
                    ? [
                        'set1_player_one_games' => 6,
                        'set1_player_two_games' => 4,
                        'set2_player_one_games' => 6,
                        'set2_player_two_games' => 3,
                    ]
                    : [
                        'set1_player_one_games' => 4,
                        'set1_player_two_games' => 6,
                        'set2_player_one_games' => 3,
                        'set2_player_two_games' => 6,
                    ],
            )->assertOk();
        } elseif (in_array($b->id, $ids, true) && in_array($c->id, $ids, true)) {
            $bIsOne = $match->player_one_id === $b->id;
            $this->actingAs($admin)->patchJson(
                route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
                $bIsOne
                    ? [
                        'set1_player_one_games' => 6,
                        'set1_player_two_games' => 2,
                        'set2_player_one_games' => 6,
                        'set2_player_two_games' => 4,
                    ]
                    : [
                        'set1_player_one_games' => 2,
                        'set1_player_two_games' => 6,
                        'set2_player_one_games' => 4,
                        'set2_player_two_games' => 6,
                    ],
            )->assertOk();
        } else {
            $cIsOne = $match->player_one_id === $c->id;
            $this->actingAs($admin)->patchJson(
                route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
                $cIsOne
                    ? [
                        'set1_player_one_games' => 6,
                        'set1_player_two_games' => 4,
                        'set2_player_one_games' => 3,
                        'set2_player_two_games' => 6,
                        'set3_player_one_games' => 6,
                        'set3_player_two_games' => 2,
                    ]
                    : [
                        'set1_player_one_games' => 4,
                        'set1_player_two_games' => 6,
                        'set2_player_one_games' => 6,
                        'set2_player_two_games' => 3,
                        'set3_player_one_games' => 2,
                        'set3_player_two_games' => 6,
                    ],
            )->assertOk();
        }
    }

    $champion = app(KnockoutBracketGeneratorService::class)->resolveChampion(
        $league->fresh(['matches.playerOne', 'matches.playerTwo', 'participants.user']),
    );

    expect($champion)->not->toBeNull();
    expect($champion['user_id'])->toBe($a->id);
});

test('final three with equal wins and set difference uses game difference', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Finalna trojka gemovi',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $a = $players[0];
    $b = $players[1];
    $c = $players[2];

    foreach ($league->matches()->orderBy('bracket_position')->get() as $match) {
        $ids = [$match->player_one_id, $match->player_two_id];

        // Circular wins; A has best game margin (6-1 win, 4-6 loss = +3).
        if (in_array($a->id, $ids, true) && in_array($b->id, $ids, true)) {
            $aIsOne = $match->player_one_id === $a->id;
            recordBestOfOneWin($match, $admin, $aIsOne ? 6 : 1, $aIsOne ? 1 : 6);
        } elseif (in_array($b->id, $ids, true) && in_array($c->id, $ids, true)) {
            $bIsOne = $match->player_one_id === $b->id;
            recordBestOfOneWin($match, $admin, $bIsOne ? 6 : 4, $bIsOne ? 4 : 6);
        } else {
            $cIsOne = $match->player_one_id === $c->id;
            recordBestOfOneWin($match, $admin, $cIsOne ? 6 : 4, $cIsOne ? 4 : 6);
        }
    }

    $champion = app(KnockoutBracketGeneratorService::class)->resolveChampion(
        $league->fresh(['matches.playerOne', 'matches.playerTwo', 'participants.user']),
    );

    expect($champion)->not->toBeNull();
    expect($champion['user_id'])->toBe($a->id);
});

test('best of five result is accepted for knockout', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(2)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Bo5 kup',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 5,
    ));

    $match = $league->matches()->where('is_bye', false)->first();

    $response = $this->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
        [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 4,
            'set2_player_one_games' => 3,
            'set2_player_two_games' => 6,
            'set3_player_one_games' => 6,
            'set3_player_two_games' => 2,
            'set4_player_one_games' => 4,
            'set4_player_two_games' => 6,
            'set5_player_one_games' => 7,
            'set5_player_two_games' => 5,
        ],
    );

    $response->assertOk();
    $match->refresh();
    expect($match->set5_player_one_games)->toBe(7);

    $champion = app(KnockoutBracketGeneratorService::class)->resolveChampion($league->fresh(['matches.playerOne', 'matches.playerTwo', 'participants.user']));
    expect($champion['user_id'])->toBe($match->player_one_id);
});

test('cannot add participants to knockout tournament after creation', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(2)->create();
    $extra = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Zatvoreni kup',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
    ));

    $response = $this->actingAs($admin)->postJson(
        route('leagues.participants.store', $league),
        ['user_id' => $extra->id],
    );

    $response->assertUnprocessable();
});

test('round robin league creation still works', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Klasicna liga',
        'rounds' => 1,
        'participant_ids' => $players->pluck('id')->all(),
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Klasicna liga')->first();

    expect($league->format)->toBe(LeagueFormat::RoundRobin);
    expect($league->sets_best_of)->toBe(3);
    expect($league->matches()->count())->toBe(3);
});

test('knockout tournament accepts guest participants', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $player = User::factory()->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Kup s gostom',
        'format' => 'knockout',
        'sets_best_of' => 1,
        'participants' => [
            ['user_id' => $player->id],
            ['first_name' => 'Gost', 'last_name' => 'Igrac'],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Kup s gostom')->first();

    expect($league)->not->toBeNull();
    expect($league->participants()->count())->toBe(2);
    expect($league->participants()->whereNull('user_id')->count())->toBe(1);
    expect($league->matches()->count())->toBe(1);

    $match = $league->matches()->first();
    expect($match->player_one_participant_id)->not->toBeNull();
    expect($match->player_two_participant_id)->not->toBeNull();
});

test('random draw mode creates at most one bye for odd player counts', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(7)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Random kup',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 3,
        knockoutDrawMode: KnockoutDrawMode::Random,
    ));

    expect($league->knockout_draw_mode)->toBe(KnockoutDrawMode::Random);
    expect($league->matches()->where('bracket_round', 1)->count())->toBe(4);
    expect($league->matches()->where('is_bye', true)->count())->toBe(1);
    expect(LeagueParticipant::query()->where('league_id', $league->id)->where('received_bye', true)->count())->toBe(1);
});

test('admin can create a doubles knockout tournament with two pairs', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Turnir parova',
        'format' => 'knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 3,
        'knockout_draw_mode' => 'seeded',
        'pairs' => [
            [$players[0]->id, $players[1]->id],
            [$players[2]->id, $players[3]->id],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->first();

    expect($league)->not->toBeNull();
    expect($league->format)->toBe(LeagueFormat::Knockout);
    expect($league->participant_mode)->toBe(LeagueParticipantMode::Doubles);
    expect($league->participants()->count())->toBe(2);

    $firstPair = $league->participants()->where('seed', 1)->first();
    expect($firstPair->user_id)->toBe($players[0]->id);
    expect($firstPair->partner_user_id)->toBe($players[1]->id);

    $match = $league->matches()->where('is_bye', false)->first();
    expect($match)->not->toBeNull();
    expect($match->player_one_id)->toBe($players[0]->id);
    expect($match->player_one_partner_id)->toBe($players[1]->id);
    expect($match->player_two_id)->toBe($players[2]->id);
    expect($match->player_two_partner_id)->toBe($players[3]->id);
    expect($match->playerOneDisplayName())->toContain(' / ');
});

test('doubles knockout with five pairs has one bye', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(10)->create();

    $pairs = [];
    for ($i = 0; $i < 10; $i += 2) {
        $pairs[] = [$players[$i]->id, $players[$i + 1]->id];
    }

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Parovi bye',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::Knockout,
        participantMode: LeagueParticipantMode::Doubles,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
        pairs: $pairs,
    ));

    expect($league->participants()->count())->toBe(5);
    expect($league->matches()->where('bracket_round', 1)->count())->toBe(3);
    expect($league->matches()->where('is_bye', true)->count())->toBe(1);

    $byeMatch = $league->matches()->where('is_bye', true)->first();
    $seedOne = $league->participants()->where('seed', 1)->first();

    expect($byeMatch->player_one_id)->toBe($seedOne->user_id);
    expect($byeMatch->player_one_partner_id)->toBe($seedOne->partner_user_id);
});

test('doubles knockout rejects a user in two pairs', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Dupli igrac',
        'format' => 'knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 3,
        'pairs' => [
            [$players[0]->id, $players[1]->id],
            [$players[0]->id, $players[2]->id],
        ],
    ])->assertUnprocessable();
});

test('doubles knockout rejects a pair that does not have two players', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Neispravan par',
        'format' => 'knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 3,
        'pairs' => [
            [$players[0]->id],
            [$players[1]->id, $players[2]->id],
        ],
    ])->assertUnprocessable();
});

test('knockout doubles tournament accepts a guest in a pair', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Parovi s gostom',
        'format' => 'knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 1,
        'pairs' => [
            [$players[0]->id, $players[1]->id],
            [
                'player_one' => ['user_id' => $players[2]->id],
                'player_two' => ['first_name' => 'Gost', 'last_name' => 'Partner'],
            ],
        ],
    ]);

    $response->assertSuccessful();

    $league = League::query()->where('name', 'Parovi s gostom')->first();
    $match = $league->matches()->where('is_bye', false)->first();

    expect($league->participants()->count())->toBe(2);
    expect($match->player_two_partner_first_name)->toBe('Gost');
    expect($match->playerTwoDisplayName())->toContain('Gost Partner');
});

test('doubles knockout rejects a guest pair without a last name', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(2)->create();

    $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Neispravan gost',
        'format' => 'knockout',
        'participant_mode' => 'doubles',
        'sets_best_of' => 3,
        'pairs' => [
            [$players[0]->id, $players[1]->id],
            [
                'player_one' => ['first_name' => 'Gost', 'last_name' => ''],
                'player_two' => ['first_name' => 'Drugi', 'last_name' => 'Gost'],
            ],
        ],
    ])->assertUnprocessable();
});

test('finishing a doubles knockout round copies both players into the next match', function () {
    $admin = User::factory()->create();
    assignTournamentAdminRole($admin);
    $players = User::factory()->count(8)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Parovi napredovanje',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        format: LeagueFormat::Knockout,
        participantMode: LeagueParticipantMode::Doubles,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
        pairs: [
            [$players[0]->id, $players[1]->id],
            [$players[2]->id, $players[3]->id],
            [$players[4]->id, $players[5]->id],
            [$players[6]->id, $players[7]->id],
        ],
    ));

    $roundOne = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('bracket_position')
        ->get();

    expect($roundOne)->toHaveCount(2);

    foreach ($roundOne as $match) {
        recordBestOfOneWin($match, $admin);
    }

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertOk();

    $final = LeagueMatch::query()
        ->where('league_id', $league->id)
        ->where('bracket_round', 2)
        ->where('is_bye', false)
        ->first();

    expect($final)->not->toBeNull();
    expect($final->player_one_partner_id)->not->toBeNull();
    expect($final->player_two_partner_id)->not->toBeNull();

    recordBestOfOneWin($final, $admin);

    $champion = app(KnockoutBracketGeneratorService::class)->resolveChampion($final->league->fresh());

    expect($champion)->not->toBeNull();
    expect($champion['name'])->toContain(' / ');
});
