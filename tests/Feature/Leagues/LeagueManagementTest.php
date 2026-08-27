<?php

use App\Actions\Leagues\AddLeagueParticipantAction;
use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;
use App\Models\Role;
use App\Models\User;
use App\Services\Leagues\LeagueStandingsService;
use Inertia\Testing\AssertableInertia as Assert;

function assignLeagueAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function recordLeagueWin(
    User $admin,
    League $league,
    LeagueMatch $match,
    User $winner,
    User $loser,
    int $loserSet1Games = 4,
    int $loserSet2Games = 3,
): void {
    $winnerIsPlayerOne = $match->player_one_id === $winner->id;

    $payload = $winnerIsPlayerOne
        ? [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => $loserSet1Games,
            'set2_player_one_games' => 6,
            'set2_player_two_games' => $loserSet2Games,
        ]
        : [
            'set1_player_one_games' => $loserSet1Games,
            'set1_player_two_games' => 6,
            'set2_player_one_games' => $loserSet2Games,
            'set2_player_two_games' => 6,
        ];

    test()->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
        $payload,
    )->assertOk();
}

test('admin can create league with participants and round robin matches', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Ljetna liga',
        'rounds' => 2,
        'participant_ids' => $players->pluck('id')->all(),
    ]);

    $response->assertCreated();

    $league = League::query()->first();
    expect($league)->not->toBeNull();
    expect($league->rounds)->toBe(2);
    expect($league->participants()->count())->toBe(3);
    expect($league->matches()->count())->toBe(6);
});

test('adding participant later generates only new matches', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(3)->create();
    $fourthPlayer = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Test liga',
        rounds: 2,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    expect($league->matches()->count())->toBe(6);

    app(AddLeagueParticipantAction::class)->execute(new AddLeagueParticipantData(
        leagueId: $league->id,
        userId: $fourthPlayer->id,
    ));

    expect($league->matches()->count())->toBe(12);
});

test('admin can record valid match result', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(2)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Duel liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $match = $league->matches()->first();
    expect($match)->not->toBeNull();

    $response = $this->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
        [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 4,
            'set2_player_one_games' => 3,
            'set2_player_two_games' => 6,
            'set3_player_one_games' => 6,
            'set3_player_two_games' => 2,
        ],
    );

    $response->assertOk();

    $match->refresh();
    expect($match->status)->toBe(LeagueMatchStatus::Played);
    expect($match->set3_player_one_games)->toBe(6);
});

test('invalid match result is rejected', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(2)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Duel liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $match = $league->matches()->first();

    $response = $this->actingAs($admin)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
        [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 6,
            'set2_player_one_games' => 4,
            'set2_player_two_games' => 2,
        ],
    );

    $response->assertUnprocessable();
});

test('standings are sorted by wins then set difference', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $playerA = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'A']);
    $playerB = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'B']);
    $playerC = User::factory()->create(['first_name' => 'Ceco', 'last_name' => 'C']);

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Poredak liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [$playerA->id, $playerB->id, $playerC->id],
    ));

    $matches = $league->matches()->get();

    $matchAB = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerA->id, $playerB->id)
            && $match->player_two_id === max($playerA->id, $playerB->id),
    );
    $matchAC = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerA->id, $playerC->id)
            && $match->player_two_id === max($playerA->id, $playerC->id),
    );
    $matchBC = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerB->id, $playerC->id)
            && $match->player_two_id === max($playerB->id, $playerC->id),
    );

    recordLeagueWin($admin, $league, $matchAB, $playerA, $playerB);
    recordLeagueWin($admin, $league, $matchAC, $playerA, $playerC);
    recordLeagueWin($admin, $league, $matchBC, $playerB, $playerC);

    $standings = app(LeagueStandingsService::class)->build($league->fresh());

    expect($standings[0]->userId)->toBe($playerA->id);
    expect($standings[0]->wins)->toBe(2);
    expect($standings[1]->userId)->toBe($playerB->id);
    expect($standings[1]->wins)->toBe(1);
});

test('standings with equal wins and set difference are sorted by game difference', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $playerA = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'A']);
    $playerB = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'B']);
    $playerC = User::factory()->create(['first_name' => 'Ceco', 'last_name' => 'C']);

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Poredak gemovi',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [$playerA->id, $playerB->id, $playerC->id],
    ));

    $matches = $league->matches()->get();

    $matchAB = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerA->id, $playerB->id)
            && $match->player_two_id === max($playerA->id, $playerB->id),
    );
    $matchAC = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerA->id, $playerC->id)
            && $match->player_two_id === max($playerA->id, $playerC->id),
    );
    $matchBC = $matches->first(
        fn (LeagueMatch $match) => $match->player_one_id === min($playerB->id, $playerC->id)
            && $match->player_two_id === max($playerB->id, $playerC->id),
    );

    recordLeagueWin($admin, $league, $matchAB, $playerA, $playerB, 0, 0);
    recordLeagueWin($admin, $league, $matchAC, $playerC, $playerA, 0, 0);
    recordLeagueWin($admin, $league, $matchBC, $playerB, $playerC, 4, 4);

    $standings = app(LeagueStandingsService::class)->build($league->fresh());

    expect($standings)->toHaveCount(3);
    expect($standings[0]->wins)->toBe(1);
    expect($standings[1]->wins)->toBe(1);
    expect($standings[2]->wins)->toBe(1);
    expect($standings[0]->setDifference)->toBe(0);
    expect($standings[0]->userId)->toBe($playerC->id);
    expect($standings[0]->gameDifference)->toBe(8);
    expect($standings[1]->userId)->toBe($playerA->id);
    expect($standings[1]->gameDifference)->toBe(0);
    expect($standings[2]->userId)->toBe($playerB->id);
    expect($standings[2]->gameDifference)->toBe(-8);
});

test('non admin cannot create league or record result', function () {
    $user = User::factory()->create();
    $players = User::factory()->count(2)->create();

    $this->actingAs($user)->postJson(route('leagues.store'), [
        'name' => 'Zabranjena liga',
        'rounds' => 1,
        'participant_ids' => $players->pluck('id')->all(),
    ])->assertForbidden();

    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Postojeca liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $match = $league->matches()->first();

    $this->actingAs($user)->patchJson(
        route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
        [
            'set1_player_one_games' => 6,
            'set1_player_two_games' => 4,
            'set2_player_one_games' => 6,
            'set2_player_two_games' => 2,
        ],
    )->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('leagues.destroy', $league))
        ->assertForbidden();

    expect(League::query()->whereKey($league->id)->exists())->toBeTrue();
});

test('admin can delete league with matches and participants', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Za brisanje',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $match = $league->matches()->first();
    expect($match)->not->toBeNull();

    recordLeagueWin($admin, $league, $match, $players[0], $players[1]);

    $leagueId = $league->id;
    expect($league->matches()->count())->toBe(3);
    expect($league->participants()->count())->toBe(3);
    expect($league->matches()->played()->count())->toBe(1);

    $this->actingAs($admin)
        ->deleteJson(route('leagues.destroy', $league))
        ->assertOk()
        ->assertJson(['data' => ['deleted' => true]]);

    expect(League::query()->whereKey($leagueId)->exists())->toBeFalse();
    expect(LeagueMatch::query()->where('league_id', $leagueId)->exists())->toBeFalse();
    expect(LeagueParticipant::query()->where('league_id', $leagueId)->exists())->toBeFalse();
});

test('authenticated users can view league pages', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(2)->create();
    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Javna liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $this->actingAs($user)
        ->get(route('dashboard.leagues'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues')
            ->has('leagues', 1)
            ->where('can_manage', false)
            ->has('users', 0),
        );

    $this->actingAs($user)
        ->get(route('dashboard.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues/show')
            ->where('league.name', 'Javna liga')
            ->where('can_manage', false)
            ->has('standings', 2)
            ->has('matches', 1)
            ->has('available_users', 0),
        );
});

test('guest cannot access league pages', function () {
    $this->get(route('dashboard.leagues'))->assertRedirect(route('login'));
});

test('admin can manage leagues from the shared leagues pages', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(2)->create();
    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Admin liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $this->actingAs($admin)
        ->get(route('dashboard.leagues'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues')
            ->has('leagues', 1)
            ->where('can_manage', true)
            ->has('users'),
        );

    $this->actingAs($admin)
        ->get(route('dashboard.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues/show')
            ->where('league.name', 'Admin liga')
            ->where('can_manage', true)
            ->has('available_users'),
        );
});

test('admin can create round robin league with guests', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $player = User::factory()->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Liga s gostom',
        'format' => 'round_robin',
        'rounds' => 1,
        'participants' => [
            ['user_id' => $player->id],
            ['first_name' => 'Gost', 'last_name' => 'Igrac'],
        ],
    ]);

    $response->assertCreated();

    $league = League::query()->where('name', 'Liga s gostom')->first();

    expect($league)->not->toBeNull();
    expect($league->participants()->count())->toBe(2);
    expect($league->participants()->whereNull('user_id')->count())->toBe(1);
    expect($league->matches()->count())->toBe(1);

    $match = $league->matches()->first();
    expect($match->player_one_participant_id)->not->toBeNull();
    expect($match->player_two_participant_id)->not->toBeNull();
    expect($match->playerOneDisplayName().' '.$match->playerTwoDisplayName())->toContain('Gost Igrac');
});

test('admin can create round robin doubles league', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->postJson(route('leagues.store'), [
        'name' => 'Liga parova',
        'format' => 'round_robin',
        'participant_mode' => 'doubles',
        'rounds' => 1,
        'pairs' => [
            [$players[0]->id, $players[1]->id],
            [
                'player_one' => ['user_id' => $players[2]->id],
                'player_two' => ['first_name' => 'Ana', 'last_name' => 'Gost'],
            ],
        ],
    ]);

    $response->assertCreated();

    $league = League::query()->where('name', 'Liga parova')->first();

    expect($league->participant_mode->value)->toBe('doubles');
    expect($league->participants()->count())->toBe(2);
    expect($league->matches()->count())->toBe(1);

    $match = $league->matches()->first();
    expect($match->playerOneDisplayName())->toContain(' / ');
    expect($match->playerTwoDisplayName())->toContain('Ana Gost');
});

test('admin can add a guest to a round robin league later', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $players = User::factory()->count(2)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Gost kasnije',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    expect($league->matches()->count())->toBe(1);

    $this->actingAs($admin)->postJson(route('leagues.participants.store', $league), [
        'first_name' => 'Novi',
        'last_name' => 'Gost',
    ])->assertOk();

    expect($league->participants()->count())->toBe(3);
    expect($league->matches()->count())->toBe(3);
});

test('admin can add a pair to a doubles round robin league later', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);
    $players = User::factory()->count(5)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Par kasnije',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: [],
        participantMode: \App\Enums\LeagueParticipantMode::Doubles,
        pairs: [
            [$players[0]->id, $players[1]->id],
            [$players[2]->id, $players[3]->id],
        ],
    ));

    expect($league->matches()->count())->toBe(1);

    $this->actingAs($admin)->postJson(route('leagues.participants.store', $league), [
        'user_id' => $players[4]->id,
        'partner' => [
            'first_name' => 'Nova',
            'last_name' => 'Partnerica',
        ],
    ])->assertOk();

    expect($league->participants()->count())->toBe(3);
    expect($league->matches()->count())->toBe(3);
});

test('legacy admin league routes redirect to the shared leagues pages', function () {
    $admin = User::factory()->create();
    assignLeagueAdminRole($admin);

    $players = User::factory()->count(2)->create();
    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Redirect liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $this->actingAs($admin)
        ->get(route('admin.leagues'))
        ->assertRedirect('/dashboard/leagues');

    $this->actingAs($admin)
        ->get(route('admin.leagues.show', $league))
        ->assertRedirect("/dashboard/leagues/{$league->id}");
});
