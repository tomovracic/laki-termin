<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Models\League;
use App\Services\Leagues\LeagueStandingsService;

class BuildLeaguePageDataAction
{
    public function __construct(
        protected LeagueStandingsService $standingsService,
    ) {}

    /**
     * @return array{
     *     league: array<string, mixed>,
     *     standings: list<array<string, mixed>>,
     *     matches: list<array<string, mixed>>,
     *     participants: list<array<string, mixed>>,
     *     available_users: list<array<string, mixed>>
     * }
     */
    public function execute(League $league, bool $includeAvailableUsers = false): array
    {
        $league->load([
            'participants.user',
            'matches.playerOne',
            'matches.playerTwo',
        ]);

        $standings = array_map(
            fn ($entry) => [
                'user_id' => $entry->userId,
                'first_name' => $entry->firstName,
                'last_name' => $entry->lastName,
                'name' => $entry->name,
                'matches_played' => $entry->matchesPlayed,
                'wins' => $entry->wins,
                'losses' => $entry->losses,
                'sets_won' => $entry->setsWon,
                'sets_lost' => $entry->setsLost,
                'set_difference' => $entry->setDifference,
            ],
            $this->standingsService->build($league),
        );

        $participantIds = $league->participants->pluck('user_id')->all();

        $participants = $league->participants
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name ?? '',
                'first_name' => $participant->user?->first_name ?? '',
                'last_name' => $participant->user?->last_name ?? '',
            ])
            ->values()
            ->all();

        $matches = $league->matches
            ->sortBy([
                ['status', 'asc'],
                ['round', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($match) => $this->formatMatch($match))
            ->values()
            ->all();

        $payload = [
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
                'rounds' => $league->rounds,
                'participants_count' => count($participants),
                'matches_count' => count($matches),
                'played_matches_count' => $league->matches->where('status', \App\Enums\LeagueMatchStatus::Played)->count(),
            ],
            'standings' => $standings,
            'matches' => $matches,
            'participants' => $participants,
            'available_users' => [],
        ];

        if ($includeAvailableUsers) {
            $payload['available_users'] = \App\Models\User::query()
                ->whereNotIn('id', $participantIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ])
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMatch(\App\Models\LeagueMatch $match): array
    {
        return [
            'id' => $match->id,
            'round' => $match->round,
            'status' => $match->status->value,
            'player_one' => [
                'id' => $match->player_one_id,
                'name' => $match->playerOne?->name ?? '',
                'first_name' => $match->playerOne?->first_name ?? '',
                'last_name' => $match->playerOne?->last_name ?? '',
            ],
            'player_two' => [
                'id' => $match->player_two_id,
                'name' => $match->playerTwo?->name ?? '',
                'first_name' => $match->playerTwo?->first_name ?? '',
                'last_name' => $match->playerTwo?->last_name ?? '',
            ],
            'set1_player_one_games' => $match->set1_player_one_games,
            'set1_player_two_games' => $match->set1_player_two_games,
            'set2_player_one_games' => $match->set2_player_one_games,
            'set2_player_two_games' => $match->set2_player_two_games,
            'set3_player_one_games' => $match->set3_player_one_games,
            'set3_player_two_games' => $match->set3_player_two_games,
            'played_at' => $match->played_at?->toIso8601String(),
        ];
    }
}
