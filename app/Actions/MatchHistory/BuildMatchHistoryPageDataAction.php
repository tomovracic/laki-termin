<?php

declare(strict_types=1);

namespace App\Actions\MatchHistory;

use App\Models\LeagueMatch;
use App\Models\PlayedMatch;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildMatchHistoryPageDataAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(User $user): array
    {
        $casualMatches = PlayedMatch::query()
            ->forUser($user->id)
            ->with(['playerOne', 'playerTwo'])
            ->get()
            ->map(fn (PlayedMatch $match): array => $this->formatCasualMatch($match, $user))
            ->all();

        $leagueMatches = LeagueMatch::query()
            ->played()
            ->forUser($user->id)
            ->with(['league', 'playerOne', 'playerTwo'])
            ->get()
            ->map(fn (LeagueMatch $match): array => $this->formatLeagueMatch($match))
            ->all();

        $entries = array_merge($casualMatches, $leagueMatches);

        usort($entries, function (array $left, array $right): int {
            return strcmp($right['played_at'] ?? '', $left['played_at'] ?? '');
        });

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCasualMatch(PlayedMatch $match, User $user): array
    {
        return [
            'id' => "casual-{$match->id}",
            'source' => 'casual',
            'played_at' => $match->played_at?->toIso8601String(),
            'player_one' => [
                'user_id' => $match->player_one_user_id,
                'name' => $match->playerOneDisplayName(),
            ],
            'player_two' => [
                'user_id' => $match->player_two_user_id,
                'name' => $match->playerTwoDisplayName(),
            ],
            'set1_player_one_games' => $match->set1_player_one_games,
            'set1_player_two_games' => $match->set1_player_two_games,
            'set2_player_one_games' => $match->set2_player_one_games,
            'set2_player_two_games' => $match->set2_player_two_games,
            'set3_player_one_games' => $match->set3_player_one_games,
            'set3_player_two_games' => $match->set3_player_two_games,
            'league' => null,
            'can_edit' => Gate::forUser($user)->allows('update', $match),
            'can_delete' => Gate::forUser($user)->allows('delete', $match),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLeagueMatch(LeagueMatch $match): array
    {
        return [
            'id' => "league-{$match->id}",
            'source' => 'league',
            'played_at' => $match->played_at?->toIso8601String(),
            'player_one' => [
                'user_id' => $match->player_one_id,
                'name' => $match->playerOne?->name ?? '',
            ],
            'player_two' => [
                'user_id' => $match->player_two_id,
                'name' => $match->playerTwo?->name ?? '',
            ],
            'set1_player_one_games' => $match->set1_player_one_games,
            'set1_player_two_games' => $match->set1_player_two_games,
            'set2_player_one_games' => $match->set2_player_one_games,
            'set2_player_two_games' => $match->set2_player_two_games,
            'set3_player_one_games' => $match->set3_player_one_games,
            'set3_player_two_games' => $match->set3_player_two_games,
            'league' => [
                'id' => $match->league_id,
                'name' => $match->league?->name ?? '',
                'round' => $match->round,
            ],
            'can_edit' => false,
            'can_delete' => false,
        ];
    }
}
