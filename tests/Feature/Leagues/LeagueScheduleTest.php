<?php

use App\Actions\Leagues\AddLeagueParticipantAction;
use App\Actions\Leagues\CreateLeagueAction;
use App\DTO\Leagues\AddLeagueParticipantData;
use App\DTO\Leagues\CreateLeagueData;
use App\DTO\Leagues\LeagueGroupInputData;
use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueFormat;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\Role;
use App\Models\User;
use App\Services\Leagues\LeagueScheduleService;
use Inertia\Testing\AssertableInertia as Assert;

function assignScheduleAdminRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => 'admin']);
    $user->roles()->syncWithoutDetaching([$role->id]);
}

function orderedScheduleMatches(League $league)
{
    return $league->matches()
        ->whereNotNull('schedule_order')
        ->orderBy('schedule_order')
        ->get()
        ->values();
}

test('round robin matches get a wait-minimizing schedule on create', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(4)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Raspored liga',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $ordered = orderedScheduleMatches($league);

    expect($ordered)->toHaveCount(6);
    expect($ordered->pluck('schedule_order')->all())->toBe([1, 2, 3, 4, 5, 6]);

    for ($index = 1; $index < $ordered->count(); $index++) {
        $previousIds = [
            $ordered[$index - 1]->player_one_participant_id,
            $ordered[$index - 1]->player_two_participant_id,
        ];
        $remaining = $ordered->slice($index);
        $hasDisjointAlternative = $remaining->contains(function (LeagueMatch $match) use ($previousIds): bool {
            $ids = [$match->player_one_participant_id, $match->player_two_participant_id];

            return array_intersect($ids, $previousIds) === [];
        });
        $currentSharesPlayer = array_intersect(
            [$ordered[$index]->player_one_participant_id, $ordered[$index]->player_two_participant_id],
            $previousIds,
        ) !== [];

        if ($hasDisjointAlternative) {
            expect($currentSharesPlayer)->toBeFalse();
        }
    }
});

test('league show page exposes schedule order on matches', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(2)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Raspored stranica',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $this->actingAs($admin)
        ->get(route('dashboard.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues/show')
            ->has('matches', 1)
            ->where('matches.0.schedule_order', 1),
        );
});

test('adding a participant keeps existing schedule order and appends new matches', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(3)->create();
    $fourth = User::factory()->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Raspored dodavanje',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $original = $league->matches()
        ->get()
        ->mapWithKeys(fn (LeagueMatch $match) => [$match->id => $match->schedule_order])
        ->all();

    expect($original)->toHaveCount(3);

    app(AddLeagueParticipantAction::class)->execute(new AddLeagueParticipantData(
        leagueId: $league->id,
        userId: $fourth->id,
    ));

    foreach ($original as $matchId => $order) {
        expect(LeagueMatch::query()->find($matchId)?->schedule_order)->toBe($order);
    }

    $newMatches = $league->matches()
        ->whereNotIn('id', array_keys($original))
        ->orderBy('schedule_order')
        ->get();

    expect($newMatches)->toHaveCount(3);
    expect($newMatches->min('schedule_order'))->toBeGreaterThan(max($original));
    expect($newMatches->every(fn (LeagueMatch $match): bool => $match->schedule_order !== null))->toBeTrue();
});

test('schedule order is kept after the round robin tournament finishes', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Zavrseni raspored',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    $ordersBefore = $league->matches()
        ->orderBy('id')
        ->pluck('schedule_order', 'id')
        ->all();

    foreach ($league->matches()->get() as $match) {
        $winner = User::query()->findOrFail($match->player_one_id);
        $loser = User::query()->findOrFail($match->player_two_id);
        recordLeagueWin($admin, $league, $match, $winner, $loser);
    }

    app(LeagueScheduleService::class)->synchronize($league->fresh());

    $ordersAfter = $league->matches()
        ->orderBy('id')
        ->pluck('schedule_order', 'id')
        ->all();

    expect($ordersAfter)->toBe($ordersBefore);
    expect($league->matches()->where('status', LeagueMatchStatus::Played->value)->count())->toBe(3);
});

test('missing schedule order is backfilled when the league page is opened', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(3)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Stari raspored',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
    ));

    LeagueMatch::query()->where('league_id', $league->id)->update(['schedule_order' => null]);

    $this->actingAs($admin)
        ->get(route('dashboard.leagues.show', $league))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/leagues/show')
            ->has('matches', 3),
        );

    expect($league->matches()->whereNull('schedule_order')->count())->toBe(0);
    expect($league->matches()->whereNotNull('schedule_order')->count())->toBe(3);
});

test('knockout byes are excluded from the schedule and next round is appended', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(5)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Knockout raspored',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::Knockout,
        setsBestOf: 1,
        knockoutDrawMode: KnockoutDrawMode::Seeded,
    ));

    $roundOne = $league->matches()
        ->where('is_bye', false)
        ->where('bracket_round', 1)
        ->orderBy('schedule_order')
        ->get();

    expect($roundOne)->toHaveCount(2);
    expect($roundOne->pluck('schedule_order')->all())->toBe([1, 2]);
    expect($league->matches()->where('is_bye', true)->count())->toBe(1);
    expect($league->matches()->where('is_bye', true)->whereNotNull('schedule_order')->count())->toBe(0);

    foreach ($roundOne as $match) {
        $this->actingAs($admin)->patchJson(
            route('leagues.matches.result.update', ['league' => $league, 'match' => $match]),
            [
                'set1_player_one_games' => 6,
                'set1_player_two_games' => 4,
            ],
        )->assertOk();
    }

    $this->actingAs($admin)
        ->postJson(route('leagues.rounds.finish', $league))
        ->assertOk();

    $nextRound = $league->matches()
        ->where('bracket_round', 2)
        ->where('is_bye', false)
        ->orderBy('schedule_order')
        ->get();

    expect($nextRound)->toHaveCount(3);
    expect($nextRound->pluck('schedule_order')->all())->toBe([3, 4, 5]);

    $roundOneOrders = $league->matches()
        ->where('bracket_round', 1)
        ->where('is_bye', false)
        ->orderBy('schedule_order')
        ->pluck('schedule_order')
        ->all();

    expect($roundOneOrders)->toBe([1, 2]);
});

test('group knockout interleaves groups so players wait less', function () {
    $admin = User::factory()->create();
    assignScheduleAdminRole($admin);
    $players = User::factory()->count(6)->create();

    $league = app(CreateLeagueAction::class)->execute(new CreateLeagueData(
        name: 'Grupe raspored',
        rounds: 1,
        createdBy: $admin->id,
        participantIds: $players->pluck('id')->all(),
        format: LeagueFormat::GroupKnockout,
        setsBestOf: 1,
        groups: [
            new LeagueGroupInputData('A', [0, 1, 2]),
            new LeagueGroupInputData('B', [3, 4, 5]),
        ],
    ));

    $ordered = orderedScheduleMatches($league);

    expect($ordered)->toHaveCount(6);
    expect($ordered[0]->league_group_id)->not->toBe($ordered[1]->league_group_id);

    $groupIds = $ordered->pluck('league_group_id')->unique()->values();
    expect($groupIds)->toHaveCount(2);
});
