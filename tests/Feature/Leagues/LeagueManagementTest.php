<?php

use App\Actions\Leagues\AddLeagueParticipantAction;
use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\CreateLeagueData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\Role;
use App\Models\User;
use App\Services\Leagues\LeagueStandingsService;
use Inertia\Testing\AssertableInertia as Assert;

function assignLeagueAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function recordLeagueWin(User $admin, League $league, LeagueMatch $match, User $winner, User $loser): void
{
    $winnerIsPlayerOne = $match->player_one_id === $winner->id;

    $payload = $winnerIsPlayerOne
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
            ->has('leagues', 1),
        );

    $this->actingAs($user)
        ->get(route('dashboard.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues/show')
            ->where('league.name', 'Javna liga')
            ->has('standings', 2)
            ->has('matches', 1),
        );
});

test('guest cannot access league pages', function () {
    $this->get(route('dashboard.leagues'))->assertRedirect(route('login'));
});

test('admin can open league management pages', function () {
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
        ->get(route('admin.leagues'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/leagues')
            ->has('leagues', 1)
            ->has('users'),
        );

    $this->actingAs($admin)
        ->get(route('admin.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/leagues/show')
            ->where('league.name', 'Admin liga')
            ->has('available_users'),
        );
});
