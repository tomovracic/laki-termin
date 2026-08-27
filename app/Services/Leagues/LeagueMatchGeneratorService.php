<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;
use Illuminate\Support\Collection;

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

    /**
     * @param  Collection<int, LeagueParticipant>|list<LeagueParticipant>  $participants
     */
    public function generateForGroup(League $league, int $groupId, iterable $participants): void
    {
        $players = collect($participants)->values();
        $count = $players->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->createMatchesForParticipants($league, $players[$i], $players[$j], $groupId);
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
        $playerOneParticipant = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where('user_id', $playerOneId)
            ->first();
        $playerTwoParticipant = LeagueParticipant::query()
            ->where('league_id', $league->id)
            ->where('user_id', $playerTwoId)
            ->first();

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
                'player_one_participant_id' => $playerOneParticipant?->id,
                'player_two_participant_id' => $playerTwoParticipant?->id,
                'round' => $round,
                'status' => LeagueMatchStatus::Pending->value,
            ]);
        }
    }

    private function createMatchesForParticipants(
        League $league,
        LeagueParticipant $playerOne,
        LeagueParticipant $playerTwo,
        int $groupId,
    ): void {
        for ($round = 1; $round <= $league->rounds; $round++) {
            $exists = LeagueMatch::query()
                ->where('league_id', $league->id)
                ->where('league_group_id', $groupId)
                ->where('player_one_participant_id', $playerOne->id)
                ->where('player_two_participant_id', $playerTwo->id)
                ->where('round', $round)
                ->exists();

            if ($exists) {
                continue;
            }

            LeagueMatch::query()->create([
                'league_id' => $league->id,
                'league_group_id' => $groupId,
                'player_one_id' => $playerOne->user_id,
                'player_one_first_name' => $playerOne->user_id === null ? $playerOne->first_name : null,
                'player_one_last_name' => $playerOne->user_id === null ? $playerOne->last_name : null,
                'player_one_participant_id' => $playerOne->id,
                'player_two_id' => $playerTwo->user_id,
                'player_two_first_name' => $playerTwo->user_id === null ? $playerTwo->first_name : null,
                'player_two_last_name' => $playerTwo->user_id === null ? $playerTwo->last_name : null,
                'player_two_participant_id' => $playerTwo->id,
                'round' => $round,
                'status' => LeagueMatchStatus::Pending->value,
            ]);
        }
    }
}
