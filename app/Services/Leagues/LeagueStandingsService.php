<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\DTO\Leagues\LeagueStandingsEntryData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\User;

class LeagueStandingsService
{
    /**
     * @return list<LeagueStandingsEntryData>
     */
    public function build(League $league): array
    {
        $participants = $league->participants()
            ->with('user')
            ->get();

        $playedMatches = $league->matches()
            ->played()
            ->get();

        $stats = [];

        foreach ($participants as $participant) {
            $user = $participant->user;

            if ($user === null) {
                continue;
            }

            $stats[$user->id] = [
                'user' => $user,
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'sets_won' => 0,
                'sets_lost' => 0,
            ];
        }

        foreach ($playedMatches as $match) {
            if ($match->is_bye || $match->isEmptyBracketSlot()) {
                continue;
            }

            $this->applyMatchToStats($stats, $match);
        }

        $entries = array_map(function (array $row): LeagueStandingsEntryData {
            /** @var User $user */
            $user = $row['user'];

            return new LeagueStandingsEntryData(
                userId: $user->id,
                firstName: $user->first_name ?? '',
                lastName: $user->last_name ?? '',
                name: $user->name,
                matchesPlayed: $row['matches_played'],
                wins: $row['wins'],
                losses: $row['losses'],
                setsWon: $row['sets_won'],
                setsLost: $row['sets_lost'],
                setDifference: $row['sets_won'] - $row['sets_lost'],
            );
        }, array_values($stats));

        usort($entries, function (LeagueStandingsEntryData $a, LeagueStandingsEntryData $b): int {
            if ($a->wins !== $b->wins) {
                return $b->wins <=> $a->wins;
            }

            if ($a->setDifference !== $b->setDifference) {
                return $b->setDifference <=> $a->setDifference;
            }

            return strcasecmp($a->firstName, $b->firstName);
        });

        return $entries;
    }

    /**
     * @param  array<int, array{user: User, matches_played: int, wins: int, losses: int, sets_won: int, sets_lost: int}>  $stats
     */
    private function applyMatchToStats(array &$stats, LeagueMatch $match): void
    {
        if ($match->status !== LeagueMatchStatus::Played) {
            return;
        }

        $playerOneId = $match->player_one_id;
        $playerTwoId = $match->player_two_id;

        if ($playerOneId === null || $playerTwoId === null || ! isset($stats[$playerOneId], $stats[$playerTwoId])) {
            return;
        }

        $playerOneSets = $this->countSetsWonByPlayerOne($match);
        $playerTwoSets = 0;

        if ($match->set1_player_one_games !== null && $match->set1_player_two_games !== null) {
            $playerTwoSets += $match->set1_player_one_games < $match->set1_player_two_games ? 1 : 0;
        }

        if ($match->set2_player_one_games !== null && $match->set2_player_two_games !== null) {
            $playerTwoSets += $match->set2_player_one_games < $match->set2_player_two_games ? 1 : 0;
        }

        if ($match->set3_player_one_games !== null && $match->set3_player_two_games !== null) {
            $playerTwoSets += $match->set3_player_one_games < $match->set3_player_two_games ? 1 : 0;
        }

        $stats[$playerOneId]['matches_played']++;
        $stats[$playerTwoId]['matches_played']++;
        $stats[$playerOneId]['sets_won'] += $playerOneSets;
        $stats[$playerOneId]['sets_lost'] += $playerTwoSets;
        $stats[$playerTwoId]['sets_won'] += $playerTwoSets;
        $stats[$playerTwoId]['sets_lost'] += $playerOneSets;

        if ($playerOneSets > $playerTwoSets) {
            $stats[$playerOneId]['wins']++;
            $stats[$playerTwoId]['losses']++;
        } else {
            $stats[$playerTwoId]['wins']++;
            $stats[$playerOneId]['losses']++;
        }
    }

    private function countSetsWonByPlayerOne(LeagueMatch $match): int
    {
        $setsWon = 0;

        if ($match->set1_player_one_games !== null && $match->set1_player_two_games !== null
            && $match->set1_player_one_games > $match->set1_player_two_games) {
            $setsWon++;
        }

        if ($match->set2_player_one_games !== null && $match->set2_player_two_games !== null
            && $match->set2_player_one_games > $match->set2_player_two_games) {
            $setsWon++;
        }

        if ($match->set3_player_one_games !== null && $match->set3_player_two_games !== null
            && $match->set3_player_one_games > $match->set3_player_two_games) {
            $setsWon++;
        }

        return $setsWon;
    }
}
