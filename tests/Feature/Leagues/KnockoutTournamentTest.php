<?php

use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueFormat;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\Role;
use App\Models\User;

function assignTournamentAdminRole(User $user): void
{

    $role = Role::query()->firstOrCreate(['name' => 'admin']);

    $user->roles()->syncWithoutDetaching([$role->id]);

}

test('admin can create knockout tournament with at most one bye for five players', function () {

    $admin = User::factory()->create();

    assignTournamentAdminRole($admin);

    $players = User::factory()->count(5)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [

        'name' => 'Knockout kup',

        'format' => 'knockout',

        'sets_best_of' => 3,

        'participant_ids' => $players->pluck('id')->all(),

    ]);

    $response->assertSuccessful();

    $league = League::query()->first();

    expect($league)->not->toBeNull();

    expect($league->format)->toBe(LeagueFormat::Knockout);

    expect($league->sets_best_of)->toBe(3);

    expect($league->participants()->count())->toBe(5);

    expect($league->participants()->whereNull('user_id')->count())->toBe(0);

    // Bracket size 8 => 7 matches total (4 R1 + 2 R2 + 1 final)

    expect($league->matches()->count())->toBe(7);

    expect($league->matches()->where('bracket_round', 1)->count())->toBe(4);

    // Max one player bye; remaining vacant slots become one empty match.
    expect($league->matches()->where('is_bye', true)->count())->toBe(1);

    $emptyRoundOne = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->whereNull('player_one_id')
        ->whereNull('player_two_id')
        ->where('status', LeagueMatchStatus::Played->value)
        ->count();

    expect($emptyRoundOne)->toBe(1);

    expect(
        $league->matches()
            ->where('bracket_round', 1)
            ->where('is_bye', false)
            ->where('status', LeagueMatchStatus::Pending->value)
            ->count()
    )->toBe(2);

});

test('knockout with six players has three first-round matches and one empty slot', function () {

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

    ));

    expect($league->matches()->where('is_bye', true)->count())->toBe(0);

    expect($league->matches()->where('bracket_round', 1)->count())->toBe(4);

    expect(
        $league->matches()
            ->where('bracket_round', 1)
            ->where('is_bye', false)
            ->where('status', LeagueMatchStatus::Pending->value)
            ->whereNotNull('player_one_id')
            ->whereNotNull('player_two_id')
            ->count()
    )->toBe(3);

    expect(
        $league->matches()
            ->where('bracket_round', 1)
            ->where('is_bye', false)
            ->whereNull('player_one_id')
            ->whereNull('player_two_id')
            ->where('status', LeagueMatchStatus::Played->value)
            ->count()
    )->toBe(1);

});

test('knockout bye advances winner into next round', function () {

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

    ));

    $byeMatches = $league->matches()->where('is_bye', true)->get();

    expect($byeMatches)->not->toBeEmpty();

    foreach ($byeMatches as $byeMatch) {

        expect($byeMatch->next_match_id)->not->toBeNull();

        $next = LeagueMatch::query()->find($byeMatch->next_match_id);

        expect($next)->not->toBeNull();

        $advanced =

            ($byeMatch->next_match_slot === 1 && (

                $next->player_one_id === $byeMatch->player_one_id

                || $next->player_one_id === $byeMatch->player_two_id

            ))

            || ($byeMatch->next_match_slot === 2 && (

                $next->player_two_id === $byeMatch->player_one_id

                || $next->player_two_id === $byeMatch->player_two_id

            ));

        expect($advanced)->toBeTrue();

    }

});

test('recording knockout result advances winner and supports best of one', function () {

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

    ));

    $match = $league->matches()

        ->where('bracket_round', 1)

        ->where('is_bye', false)

        ->where('status', LeagueMatchStatus::Pending->value)

        ->orderBy('bracket_position')

        ->first();

    expect($match)->not->toBeNull();

    expect($match->next_match_id)->not->toBeNull();

    $response = $this->actingAs($admin)->patchJson(

        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),

        [

            'set1_player_one_games' => 6,

            'set1_player_two_games' => 4,

        ],

    );

    $response->assertOk();

    $match->refresh();

    expect($match->status)->toBe(LeagueMatchStatus::Played);

    $next = LeagueMatch::query()->find($match->next_match_id);

    expect($next)->not->toBeNull();

    if ($match->next_match_slot === 1) {

        expect($next->player_one_id)->toBe($match->player_one_id);

    } else {

        expect($next->player_two_id)->toBe($match->player_one_id);

    }

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

test('knockout tournament rejects guest participants', function () {

    $admin = User::factory()->create();

    assignTournamentAdminRole($admin);

    $players = User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [

        'name' => 'Neispravan kup',

        'format' => 'knockout',

        'sets_best_of' => 3,

        'participants' => [

            ['user_id' => $players[0]->id],

            ['first_name' => 'Gost', 'last_name' => 'Igrac'],

        ],

    ]);

    $response->assertUnprocessable();

    expect(League::query()->count())->toBe(0);

});
