<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\DTO\Leagues\LeagueStandingsEntryData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueGroup;
use App\Models\LeagueParticipant;

class GroupQualificationService
{
    public function __construct(
        protected LeagueStandingsService $standingsService,
        protected GroupStageValidator $validator,
    ) {}

    /**
     * @return list<LeagueStandingsEntryData>
     */
    public function qualify(League $league): array
    {
        $league->loadMissing(['groups.participants.user', 'groups.participants.group', 'matches']);

        $qualifyPerGroup = (int) ($league->qualify_per_group ?? 1);
        $bestRunnersUp = (int) ($league->best_runners_up ?? 0);
        $groups = $league->groups->sortBy('sort_order')->values();

        $this->validator->validatePersistedGroups(
            $groups->all(),
            $qualifyPerGroup,
            $bestRunnersUp,
        );

        $automatic = [];
        $bestOfRestPool = [];

        foreach ($groups as $group) {
            $standings = $this->standingsForGroup($league, $group);

            foreach ($standings as $index => $entry) {
                $ranked = new LeagueStandingsEntryData(
                    participantId: $entry->participantId,
                    userId: $entry->userId,
                    firstName: $entry->firstName,
                    lastName: $entry->lastName,
                    name: $entry->name,
                    matchesPlayed: $entry->matchesPlayed,
                    wins: $entry->wins,
                    losses: $entry->losses,
                    setsWon: $entry->setsWon,
                    setsLost: $entry->setsLost,
                    setDifference: $entry->setDifference,
                    gamesWon: $entry->gamesWon,
                    gamesLost: $entry->gamesLost,
                    gameDifference: $entry->gameDifference,
                    groupId: $group->id,
                    groupName: $group->name,
                    rankInGroup: $index + 1,
                );

                if ($index < $qualifyPerGroup) {
                    $automatic[] = $ranked;

                    continue;
                }

                if ($index === $qualifyPerGroup) {
                    $bestOfRestPool[] = $ranked;
                }
            }
        }

        usort($automatic, function (LeagueStandingsEntryData $a, LeagueStandingsEntryData $b): int {
            $aRank = $a->rankInGroup ?? PHP_INT_MAX;
            $bRank = $b->rankInGroup ?? PHP_INT_MAX;

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return $this->standingsService->compareEntries($a, $b);
        });

        usort($bestOfRestPool, $this->standingsService->compareEntries(...));

        $wildcards = array_slice($bestOfRestPool, 0, $bestRunnersUp);

        return [...$automatic, ...$wildcards];
    }

    /**
     * @return list<LeagueParticipant>
     */
    public function qualifyingParticipants(League $league): array
    {
        $league->loadMissing('participants');
        $byId = $league->participants->keyBy('id');
        $participants = [];

        foreach ($this->qualify($league) as $entry) {
            $participant = $byId->get($entry->participantId);

            if ($participant instanceof LeagueParticipant) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }

    public function isGroupStageComplete(League $league): bool
    {
        $groupMatches = $league->matches()
            ->whereNotNull('league_group_id')
            ->where('is_bye', false)
            ->get();

        if ($groupMatches->isEmpty()) {
            return false;
        }

        return $groupMatches->every(
            fn ($match): bool => $match->status === LeagueMatchStatus::Played,
        );
    }

    /**
     * @return list<LeagueStandingsEntryData>
     */
    private function standingsForGroup(League $league, LeagueGroup $group): array
    {
        $participants = $group->participants;
        $playedMatches = $league->matches
            ->where('league_group_id', $group->id)
            ->where('status', LeagueMatchStatus::Played);

        return $this->standingsService->buildFrom($participants, $playedMatches);
    }
}
