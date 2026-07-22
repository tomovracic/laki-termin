<?php

declare(strict_types=1);

namespace App\Services\Ranking;

use App\DTO\Ranking\EloRankingEntryData;
use App\Enums\LeagueMatchStatus;
use App\Models\LeagueMatch;
use App\Models\PlayedMatch;
use App\Models\User;

class EloRankingService
{
    /**
     * @return list<EloRankingEntryData>
     */
    public function build(): array
    {
        $initialRating = (int) config('elo.initial_rating', 1000);
        $kFactor = (int) config('elo.k_factor', 32);

        /** @var array<int, array{user: User, elo: float, matches_played: int, wins: int, losses: int}> $ratings */
        $ratings = [];

        foreach ($this->ratedMatchesChronologically() as $match) {
            $playerOne = $match['player_one'];
            $playerTwo = $match['player_two'];

            if ($playerOne === null || $playerTwo === null) {
                continue;
            }

            $this->ensurePlayer($ratings, $playerOne, $initialRating);
            $this->ensurePlayer($ratings, $playerTwo, $initialRating);

            $playerOneId = $playerOne->id;
            $playerTwoId = $playerTwo->id;
            $playerOneWon = $match['player_one_won'];

            $ratingA = $ratings[$playerOneId]['elo'];
            $ratingB = $ratings[$playerTwoId]['elo'];

            $expectedA = $this->expectedScore($ratingA, $ratingB);
            $expectedB = $this->expectedScore($ratingB, $ratingA);

            $scoreA = $playerOneWon ? 1.0 : 0.0;
            $scoreB = $playerOneWon ? 0.0 : 1.0;

            $ratings[$playerOneId]['elo'] = $ratingA + ($kFactor * ($scoreA - $expectedA));
            $ratings[$playerTwoId]['elo'] = $ratingB + ($kFactor * ($scoreB - $expectedB));

            $ratings[$playerOneId]['matches_played']++;
            $ratings[$playerTwoId]['matches_played']++;

            if ($playerOneWon) {
                $ratings[$playerOneId]['wins']++;
                $ratings[$playerTwoId]['losses']++;
            } else {
                $ratings[$playerTwoId]['wins']++;
                $ratings[$playerOneId]['losses']++;
            }
        }

        $entries = array_map(function (array $row): EloRankingEntryData {
            /** @var User $user */
            $user = $row['user'];

            return new EloRankingEntryData(
                userId: $user->id,
                firstName: $user->first_name ?? '',
                lastName: $user->last_name ?? '',
                name: $user->name,
                elo: (int) round($row['elo']),
                matchesPlayed: $row['matches_played'],
                wins: $row['wins'],
                losses: $row['losses'],
            );
        }, array_values($ratings));

        usort($entries, function (EloRankingEntryData $a, EloRankingEntryData $b): int {
            if ($a->elo !== $b->elo) {
                return $b->elo <=> $a->elo;
            }

            if ($a->wins !== $b->wins) {
                return $b->wins <=> $a->wins;
            }

            if ($a->matchesPlayed !== $b->matchesPlayed) {
                return $b->matchesPlayed <=> $a->matchesPlayed;
            }

            return strcasecmp($a->firstName, $b->firstName);
        });

        return $entries;
    }

    /**
     * @return list<array{player_one: User, player_two: User, player_one_won: bool, sort_key: string}>
     */
    private function ratedMatchesChronologically(): array
    {
        $casual = PlayedMatch::query()
            ->whereNotNull('player_one_user_id')
            ->whereNotNull('player_two_user_id')
            ->with(['playerOne', 'playerTwo'])
            ->get()
            ->map(function (PlayedMatch $match): ?array {
                if ($match->playerOne === null || $match->playerTwo === null) {
                    return null;
                }

                $playedAt = $match->played_at?->toIso8601String() ?? '';

                return [
                    'player_one' => $match->playerOne,
                    'player_two' => $match->playerTwo,
                    'player_one_won' => $this->playerOneWonSets($match),
                    'sort_key' => $playedAt.'|casual|'.$match->id,
                ];
            })
            ->filter()
            ->values();

        $league = LeagueMatch::query()
            ->played()
            ->whereNotNull('player_one_id')
            ->whereNotNull('player_two_id')
            ->with(['playerOne', 'playerTwo'])
            ->get()
            ->map(function (LeagueMatch $match): ?array {
                if ($match->is_bye || $match->isEmptyBracketSlot()) {
                    return null;
                }

                if ($match->status !== LeagueMatchStatus::Played) {
                    return null;
                }

                if ($match->playerOne === null || $match->playerTwo === null) {
                    return null;
                }

                $playedAt = $match->played_at?->toIso8601String() ?? '';

                return [
                    'player_one' => $match->playerOne,
                    'player_two' => $match->playerTwo,
                    'player_one_won' => $this->playerOneWonSets($match),
                    'sort_key' => $playedAt.'|league|'.$match->id,
                ];
            })
            ->filter()
            ->values();

        return $casual
            ->concat($league)
            ->sortBy('sort_key')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{user: User, elo: float, matches_played: int, wins: int, losses: int}>  $ratings
     */
    private function ensurePlayer(array &$ratings, User $user, int $initialRating): void
    {
        if (isset($ratings[$user->id])) {
            return;
        }

        $ratings[$user->id] = [
            'user' => $user,
            'elo' => (float) $initialRating,
            'matches_played' => 0,
            'wins' => 0,
            'losses' => 0,
        ];
    }

    private function expectedScore(float $ratingA, float $ratingB): float
    {
        return 1 / (1 + (10 ** (($ratingB - $ratingA) / 400)));
    }

    private function playerOneWonSets(PlayedMatch|LeagueMatch $match): bool
    {
        $playerOneSets = 0;
        $playerTwoSets = 0;

        $sets = [
            [$match->set1_player_one_games, $match->set1_player_two_games],
            [$match->set2_player_one_games, $match->set2_player_two_games],
            [$match->set3_player_one_games, $match->set3_player_two_games],
        ];

        if ($match instanceof LeagueMatch) {
            $sets[] = [$match->set4_player_one_games, $match->set4_player_two_games];
            $sets[] = [$match->set5_player_one_games, $match->set5_player_two_games];
        }

        foreach ($sets as [$playerOneGames, $playerTwoGames]) {
            if ($playerOneGames === null || $playerTwoGames === null) {
                continue;
            }

            if ($playerOneGames > $playerTwoGames) {
                $playerOneSets++;
            } elseif ($playerTwoGames > $playerOneGames) {
                $playerTwoSets++;
            }
        }

        return $playerOneSets > $playerTwoSets;
    }
}
