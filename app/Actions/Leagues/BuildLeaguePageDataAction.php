<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\User;
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

        $standings = [];

        if (! $league->isKnockout()) {
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
        }

        $participantIds = $league->participants
            ->pluck('user_id')
            ->filter()
            ->all();

        $participants = $league->participants
            ->sortBy('seed')
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'name' => $participant->displayName(),
                'first_name' => $participant->user?->first_name ?? $participant->first_name ?? '',
                'last_name' => $participant->user?->last_name ?? $participant->last_name ?? '',
                'seed' => $participant->seed,
            ])
            ->values()
            ->all();

        $matches = $league->matches
            ->sortBy([
                ['bracket_round', 'asc'],
                ['bracket_position', 'asc'],
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
                'format' => $league->format->value,
                'rounds' => $league->rounds,
                'sets_best_of' => $league->sets_best_of,
                'participants_count' => count($participants),
                'matches_count' => count($matches),
                'played_matches_count' => $league->matches->where('status', LeagueMatchStatus::Played)->count(),
            ],
            'standings' => $standings,
            'matches' => $matches,
            'participants' => $participants,
            'available_users' => [],
        ];

        if ($includeAvailableUsers && ! $league->isKnockout()) {
            $payload['available_users'] = User::query()
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
    private function formatMatch(LeagueMatch $match): array
    {
        return [
            'id' => $match->id,
            'round' => $match->round,
            'bracket_round' => $match->bracket_round,
            'bracket_position' => $match->bracket_position,
            'next_match_id' => $match->next_match_id,
            'next_match_slot' => $match->next_match_slot,
            'is_bye' => (bool) $match->is_bye,
            'is_empty' => $match->isEmptyBracketSlot(),
            'status' => $match->status->value,
            'player_one' => $this->formatMatchPlayer(
                $match->player_one_id,
                $match->playerOneDisplayName(),
                $match->playerOne,
                $match->player_one_first_name,
                $match->player_one_last_name,
            ),
            'player_two' => $this->formatMatchPlayer(
                $match->player_two_id,
                $match->playerTwoDisplayName(),
                $match->playerTwo,
                $match->player_two_first_name,
                $match->player_two_last_name,
            ),
            'set1_player_one_games' => $match->set1_player_one_games,
            'set1_player_two_games' => $match->set1_player_two_games,
            'set2_player_one_games' => $match->set2_player_one_games,
            'set2_player_two_games' => $match->set2_player_two_games,
            'set3_player_one_games' => $match->set3_player_one_games,
            'set3_player_two_games' => $match->set3_player_two_games,
            'set4_player_one_games' => $match->set4_player_one_games,
            'set4_player_two_games' => $match->set4_player_two_games,
            'set5_player_one_games' => $match->set5_player_one_games,
            'set5_player_two_games' => $match->set5_player_two_games,
            'played_at' => $match->played_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int|null, name: string, first_name: string, last_name: string}|null
     */
    private function formatMatchPlayer(
        ?int $userId,
        string $displayName,
        mixed $user,
        ?string $firstName,
        ?string $lastName,
    ): ?array {
        if ($userId === null && $firstName === null && $lastName === null && $displayName === '') {
            return null;
        }

        return [
            'id' => $userId,
            'name' => $displayName !== '' ? $displayName : trim(($firstName ?? '').' '.($lastName ?? '')),
            'first_name' => $user?->first_name ?? $firstName ?? '',
            'last_name' => $user?->last_name ?? $lastName ?? '',
        ];
    }
}
