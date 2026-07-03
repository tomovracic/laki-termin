<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;

class LeagueMatchGeneratorService
{
    /**
     * @param  list<int>  $participantIds
     */
    public function generateForAllParticipants(League $league, array $participantIds): void
    {
        $uniqueIds = array_values(array_unique($participantIds));
        sort($uniqueIds);

        $count = count($uniqueIds);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->createMatchesForPair($league, $uniqueIds[$i], $uniqueIds[$j]);
            }
        }
    }

    public function generateForNewParticipant(League $league, int $newUserId, array $existingParticipantIds): void
    {
        foreach ($existingParticipantIds as $existingUserId) {
            if ($existingUserId === $newUserId) {
                continue;
            }

            $playerOneId = min($newUserId, $existingUserId);
            $playerTwoId = max($newUserId, $existingUserId);

            $this->createMatchesForPairIds($league, $playerOneId, $playerTwoId);
        }
    }

    private function createMatchesForPair(League $league, int $userA, int $userB): void
    {
        $playerOneId = min($userA, $userB);
        $playerTwoId = max($userA, $userB);

        $this->createMatchesForPairIds($league, $playerOneId, $playerTwoId);
    }

    private function createMatchesForPairIds(League $league, int $playerOneId, int $playerTwoId): void
    {
        for ($round = 1; $round <= $league->rounds; $round++) {
            $exists = LeagueMatch::query()
                ->where('league_id', $league->id)
                ->where('player_one_id', $playerOneId)
                ->where('player_two_id', $playerTwoId)
                ->where('round', $round)
                ->exists();

            if ($exists) {
                continue;
            }

            LeagueMatch::query()->create([
                'league_id' => $league->id,
                'player_one_id' => $playerOneId,
                'player_two_id' => $playerTwoId,
                'round' => $round,
                'status' => LeagueMatchStatus::Pending->value,
            ]);
        }
    }
}
