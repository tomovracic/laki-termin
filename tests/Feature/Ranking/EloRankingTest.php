<?php

use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\PlayedMatch;
use App\Models\User;
use App\Services\Ranking\EloRankingService;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('authenticated user can view empty ranking page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.ranking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ranking')
            ->has('rankings', 0));
});

test('guest cannot view ranking page', function () {
    $this->get(route('dashboard.ranking'))->assertRedirect();
});

test('guest opponent matches are excluded from elo ranking', function () {
    $player = User::factory()->create();

    PlayedMatch::factory()->withGuestOpponent()->create([
        'player_one_user_id' => $player->id,
        'entered_by' => $player->id,
        'played_at' => Date::now()->subDay(),
    ]);

    $rankings = app(EloRankingService::class)->build();

    expect($rankings)->toBeEmpty();
});

test('non ranked casual matches are excluded from elo ranking', function () {
    $playerOne = User::factory()->create();
    $playerTwo = User::factory()->create();

    PlayedMatch::factory()->create([
        'player_one_user_id' => $playerOne->id,
        'player_two_user_id' => $playerTwo->id,
        'entered_by' => $playerOne->id,
        'is_ranked' => false,
        'played_at' => Date::now()->subDay(),
    ]);

    $rankings = app(EloRankingService::class)->build();

    expect($rankings)->toBeEmpty();
});

test('elo ranking ranks winner above loser after one rated match', function () {
    $winner = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pobjednica']);
    $loser = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Gubitnik']);

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

    $rankings = app(EloRankingService::class)->build();

    expect($rankings)->toHaveCount(2);
    expect($rankings[0]->userId)->toBe($winner->id);
    expect($rankings[0]->elo)->toBe(1016);
    expect($rankings[0]->wins)->toBe(1);
    expect($rankings[0]->losses)->toBe(0);
    expect($rankings[1]->userId)->toBe($loser->id);
    expect($rankings[1]->elo)->toBe(984);
    expect($rankings[1]->wins)->toBe(0);
    expect($rankings[1]->losses)->toBe(1);
});

test('elo ranking includes played league matches between registered users', function () {
    $playerA = User::factory()->create(['first_name' => 'Ana']);
    $playerB = User::factory()->create(['first_name' => 'Bruno']);
    $creator = User::factory()->create();

    $league = League::query()->create([
        'name' => 'Test liga',
        'rounds' => 1,
        'created_by' => $creator->id,
    ]);

    LeagueMatch::query()->create([
        'league_id' => $league->id,
        'round' => 1,
        'player_one_id' => $playerA->id,
        'player_two_id' => $playerB->id,
        'status' => LeagueMatchStatus::Played,
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 2,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 1,
        'played_at' => Date::parse('2026-02-01 12:00:00'),
    ]);

    $rankings = app(EloRankingService::class)->build();

    expect($rankings)->toHaveCount(2);
    expect($rankings[0]->userId)->toBe($playerA->id);
    expect($rankings[0]->elo)->toBeGreaterThan($rankings[1]->elo);
});

test('elo updates chronologically across multiple matches', function () {
    $playerA = User::factory()->create(['first_name' => 'Ana']);
    $playerB = User::factory()->create(['first_name' => 'Bruno']);
    $playerC = User::factory()->create(['first_name' => 'Ceco']);

    PlayedMatch::factory()->create([
        'player_one_user_id' => $playerA->id,
        'player_two_user_id' => $playerB->id,
        'entered_by' => $playerA->id,
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 3,
        'played_at' => Date::parse('2026-01-01 10:00:00'),
    ]);

    PlayedMatch::factory()->create([
        'player_one_user_id' => $playerC->id,
        'player_two_user_id' => $playerA->id,
        'entered_by' => $playerC->id,
        'set1_player_one_games' => 6,
        'set1_player_two_games' => 4,
        'set2_player_one_games' => 6,
        'set2_player_two_games' => 2,
        'played_at' => Date::parse('2026-01-02 10:00:00'),
    ]);

    $rankings = app(EloRankingService::class)->build();

    expect($rankings)->toHaveCount(3);

    $byUserId = collect($rankings)->keyBy(fn ($entry) => $entry->userId);

    expect($byUserId[$playerC->id]->elo)->toBeGreaterThan($byUserId[$playerA->id]->elo);
    expect($byUserId[$playerA->id]->elo)->toBeGreaterThan($byUserId[$playerB->id]->elo);
    expect($byUserId[$playerA->id]->wins)->toBe(1);
    expect($byUserId[$playerA->id]->losses)->toBe(1);
    expect($byUserId[$playerC->id]->wins)->toBe(1);
});

test('ranking page returns computed elo entries', function () {
    $viewer = User::factory()->create();
    $winner = User::factory()->create(['first_name' => 'Ana']);
    $loser = User::factory()->create(['first_name' => 'Bruno']);

    PlayedMatch::factory()->create([
        'player_one_user_id' => $winner->id,
        'player_two_user_id' => $loser->id,
        'entered_by' => $winner->id,
        'played_at' => Date::now()->subHour(),
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.ranking'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/ranking')
            ->has('rankings', 2)
            ->where('rankings.0.user_id', $winner->id)
            ->where('rankings.0.elo', 1016)
            ->where('rankings.1.user_id', $loser->id)
            ->where('rankings.1.elo', 984));
});
