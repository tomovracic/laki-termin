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
     * @param  Collection<int, LeagueParticipant>|list<LeagueParticipant>  $participants
     */
    public function generateForAllParticipants(League $league, iterable $participants): void
    {
        $this->generateRoundRobin($league, $participants);
    }

    /**
     * @param  Collection<int, LeagueParticipant>|list<LeagueParticipant>  $participants
     */
    public function generateForGroup(League $league, int $groupId, iterable $participants): void
    {
        $this->generateRoundRobin($league, $participants, $groupId);
    }

    /**
     * @param  Collection<int, LeagueParticipant>|list<LeagueParticipant>  $existingParticipants
     */
    public function generateForNewParticipant(
        League $league,
        LeagueParticipant $newParticipant,
        iterable $existingParticipants,
    ): void {
        foreach ($existingParticipants as $existingParticipant) {
            if ($existingParticipant->id === $newParticipant->id) {
                continue;
            }

            $this->createMatchesForParticipants($league, $existingParticipant, $newParticipant);
        }
    }

    /**
     * @param  Collection<int, LeagueParticipant>|list<LeagueParticipant>  $participants
     */
    private function generateRoundRobin(League $league, iterable $participants, ?int $groupId = null): void
    {
        $players = collect($participants)->values();
        $count = $players->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->createMatchesForParticipants($league, $players[$i], $players[$j], $groupId);
            }
        }
    }

    private function createMatchesForParticipants(
        League $league,
        LeagueParticipant $playerOne,
        LeagueParticipant $playerTwo,
        ?int $groupId = null,
    ): void {
        for ($round = 1; $round <= $league->rounds; $round++) {
            $existsQuery = LeagueMatch::query()
                ->where('league_id', $league->id)
                ->where('round', $round)
                ->where(function ($query) use ($playerOne, $playerTwo): void {
                    $query->where(function ($inner) use ($playerOne, $playerTwo): void {
                        $inner->where('player_one_participant_id', $playerOne->id)
                            ->where('player_two_participant_id', $playerTwo->id);
                    })->orWhere(function ($inner) use ($playerOne, $playerTwo): void {
                        $inner->where('player_one_participant_id', $playerTwo->id)
                            ->where('player_two_participant_id', $playerOne->id);
                    });
                });

            if ($groupId !== null) {
                $existsQuery->where('league_group_id', $groupId);
            }

            if ($existsQuery->exists()) {
                continue;
            }

            LeagueMatch::query()->create([
                'league_id' => $league->id,
                'league_group_id' => $groupId,
                ...$playerOne->matchSlotAttributes(1),
                ...$playerTwo->matchSlotAttributes(2),
                'round' => $round,
                'status' => LeagueMatchStatus::Pending->value,
            ]);
        }
    }
}
