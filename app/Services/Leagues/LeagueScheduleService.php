<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\LeagueMatch;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class LeagueScheduleService
{
    public function __construct(
        protected DatabaseManager $database,
    ) {}

    /**
     * Assign a persistent play order to matches that do not have one yet.
     *
     * Already ordered matches keep their place so a finished tournament
     * (and any published schedule) stays intact. New matches are appended
     * so that players who have been idle the longest play next.
     */
    public function synchronize(League $league): void
    {
        $this->database->transaction(function () use ($league): void {
            $matches = LeagueMatch::query()
                ->where('league_id', $league->id)
                ->orderBy('id')
                ->get();

            $playable = $matches
                ->filter(fn (LeagueMatch $match): bool => $match->isOnSchedule())
                ->values();

            $ordered = $playable
                ->filter(fn (LeagueMatch $match): bool => $match->schedule_order !== null)
                ->sortBy('schedule_order')
                ->values();

            $unordered = $playable
                ->filter(fn (LeagueMatch $match): bool => $match->schedule_order === null)
                ->values();

            if ($unordered->isEmpty()) {
                return;
            }

            $lastSlotByParticipant = [];
            $scheduledCount = 0;

            foreach ($ordered as $match) {
                foreach ($this->participantIds($match) as $participantId) {
                    $lastSlotByParticipant[$participantId] = $scheduledCount;
                }
                $scheduledCount++;
            }

            $nextOrder = (int) ($ordered->max('schedule_order') ?? 0);

            foreach ($this->unorderedBatches($unordered) as $batch) {
                foreach ($this->sequenceBatch($batch, $lastSlotByParticipant, $scheduledCount) as $match) {
                    $nextOrder++;
                    $match->forceFill(['schedule_order' => $nextOrder])->save();
                }
            }
        });
    }

    /**
     * @param  Collection<int, LeagueMatch>  $unordered
     * @return list<Collection<int, LeagueMatch>>
     */
    private function unorderedBatches(Collection $unordered): array
    {
        $batches = [];

        $roundRobin = $unordered->filter(
            fn (LeagueMatch $match): bool => $match->bracket_round === null && $match->league_group_id === null,
        );

        if ($roundRobin->isNotEmpty()) {
            $batches[] = $roundRobin->values();
        }

        $group = $unordered->filter(
            fn (LeagueMatch $match): bool => $match->league_group_id !== null,
        );

        if ($group->isNotEmpty()) {
            $batches[] = $group->values();
        }

        $knockout = $unordered->filter(
            fn (LeagueMatch $match): bool => $match->bracket_round !== null,
        );

        foreach ($knockout->groupBy('bracket_round')->sortKeys() as $roundMatches) {
            $batches[] = $roundMatches->values();
        }

        return $batches;
    }

    /**
     * @param  Collection<int, LeagueMatch>  $batch
     * @param  array<int, int>  $lastSlotByParticipant
     * @return list<LeagueMatch>
     */
    private function sequenceBatch(
        Collection $batch,
        array &$lastSlotByParticipant,
        int &$scheduledCount,
    ): array {
        $remaining = $batch->all();
        $sequence = [];

        while ($remaining !== []) {
            $bestIndex = $this->pickNextIndex($remaining, $lastSlotByParticipant, $scheduledCount);
            $match = $remaining[$bestIndex];
            unset($remaining[$bestIndex]);
            $remaining = array_values($remaining);

            $sequence[] = $match;

            foreach ($this->participantIds($match) as $participantId) {
                $lastSlotByParticipant[$participantId] = $scheduledCount;
            }

            $scheduledCount++;
        }

        return $sequence;
    }

    /**
     * @param  list<LeagueMatch>  $remaining
     * @param  array<int, int>  $lastSlotByParticipant
     */
    private function pickNextIndex(array $remaining, array $lastSlotByParticipant, int $slot): int
    {
        $bestIndex = 0;
        $bestScore = null;

        foreach ($remaining as $index => $match) {
            $score = $this->waitScore($match, $lastSlotByParticipant, $slot);

            if ($bestScore === null || $this->isBetterScore($score, $bestScore)) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * Higher score = players in this match have been waiting longer.
     *
     * @param  array<int, int>  $lastSlotByParticipant
     * @return array{0: int, 1: int, 2: int}
     */
    private function waitScore(LeagueMatch $match, array $lastSlotByParticipant, int $slot): array
    {
        $waits = [];

        foreach ($this->participantIds($match) as $participantId) {
            $lastSlot = $lastSlotByParticipant[$participantId] ?? -1;
            $waits[] = $slot - $lastSlot;
        }

        if ($waits === []) {
            return [0, 0, -$match->id];
        }

        return [array_sum($waits), max($waits), -$match->id];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $candidate
     * @param  array{0: int, 1: int, 2: int}  $currentBest
     */
    private function isBetterScore(array $candidate, array $currentBest): bool
    {
        return $candidate[0] > $currentBest[0]
            || ($candidate[0] === $currentBest[0] && $candidate[1] > $currentBest[1])
            || ($candidate[0] === $currentBest[0] && $candidate[1] === $currentBest[1] && $candidate[2] > $currentBest[2]);
    }

    /**
     * @return list<int>
     */
    private function participantIds(LeagueMatch $match): array
    {
        $ids = [];

        if ($match->player_one_participant_id !== null) {
            $ids[] = $match->player_one_participant_id;
        }

        if ($match->player_two_participant_id !== null) {
            $ids[] = $match->player_two_participant_id;
        }

        return $ids;
    }
}
