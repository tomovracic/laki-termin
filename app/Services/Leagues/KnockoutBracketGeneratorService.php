<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;

class KnockoutBracketGeneratorService
{
    /**
     * @param  list<LeagueParticipant>  $participants  ordered by seed (index 0 = seed 1)
     */
    public function generate(League $league, array $participants): void
    {
        $count = count($participants);

        if ($count < 2) {
            return;
        }

        $bracketSize = $this->nextPowerOfTwo($count);
        $totalRounds = (int) log($bracketSize, 2);

        /** @var array<int, array<int, LeagueMatch>> $matchesByRound */
        $matchesByRound = [];

        for ($round = $totalRounds; $round >= 1; $round--) {
            $matchCount = 1 << ($totalRounds - $round);
            $matchesByRound[$round] = [];

            for ($position = 0; $position < $matchCount; $position++) {
                $nextMatch = null;
                $nextSlot = null;

                if ($round < $totalRounds) {
                    $nextMatch = $matchesByRound[$round + 1][(int) floor($position / 2)];
                    $nextSlot = ($position % 2) + 1;
                }

                $match = LeagueMatch::query()->create([
                    'league_id' => $league->id,
                    'player_one_id' => null,
                    'player_two_id' => null,
                    'round' => $round,
                    'bracket_round' => $round,
                    'bracket_position' => $position,
                    'next_match_id' => $nextMatch?->id,
                    'next_match_slot' => $nextSlot,
                    'is_bye' => false,
                    'status' => LeagueMatchStatus::Pending->value,
                ]);

                $matchesByRound[$round][$position] = $match;
            }
        }

        $seedSlots = $this->firstRoundSeedSlots($count, $bracketSize);

        foreach ($matchesByRound[1] as $position => $match) {
            $leftSeed = $seedSlots[$position * 2];
            $rightSeed = $seedSlots[$position * 2 + 1];

            $leftParticipant = $leftSeed !== null ? $participants[$leftSeed - 1] : null;
            $rightParticipant = $rightSeed !== null ? $participants[$rightSeed - 1] : null;

            $this->assignParticipantToSlot($match, 1, $leftParticipant);
            $this->assignParticipantToSlot($match, 2, $rightParticipant);
            $match->save();

            $hasLeft = $leftParticipant !== null;
            $hasRight = $rightParticipant !== null;

            if ($hasLeft xor $hasRight) {
                $match->forceFill([
                    'is_bye' => true,
                    'status' => LeagueMatchStatus::Played->value,
                    'played_at' => now(),
                ])->save();

                $this->advanceWinner($match, $hasLeft ? 1 : 2);

                continue;
            }

            if (! $hasLeft && ! $hasRight) {
                $match->forceFill([
                    'status' => LeagueMatchStatus::Played->value,
                    'played_at' => now(),
                ])->save();
            }
        }
    }

    public function nextPowerOfTwo(int $n): int
    {
        if ($n < 1) {
            return 1;
        }

        $power = 1;

        while ($power < $n) {
            $power <<= 1;
        }

        return $power;
    }

    /**
     * First-round slot seeds (1-based), or null for an empty slot.
     *
     * Uses standard single-elimination placement so all byes are in round 1
     * (count = bracketSize - participantCount) and later rounds stay full.
     *
     * @return list<?int>
     */
    public function firstRoundSeedSlots(int $participantCount, int $bracketSize): array
    {
        return array_map(
            static fn (int $seed): ?int => $seed <= $participantCount ? $seed : null,
            $this->standardSeedSlots($bracketSize),
        );
    }

    /**
     * Standard single-elimination seed placement for bracket slots 0..size-1.
     *
     * @return list<int> seed numbers (1-based) for each bracket slot
     */
    public function standardSeedSlots(int $size): array
    {
        $slots = [1];

        for ($currentSize = 1; $currentSize < $size; $currentSize *= 2) {
            $next = [];

            foreach ($slots as $seed) {
                $next[] = $seed;
                $next[] = ($currentSize * 2 + 1) - $seed;
            }

            $slots = $next;
        }

        return $slots;
    }

    private function assignParticipantToSlot(LeagueMatch $match, int $slot, ?LeagueParticipant $participant): void
    {
        if ($participant === null) {
            return;
        }

        if ($slot === 1) {
            $match->player_one_id = $participant->user_id;
            $match->player_one_first_name = $participant->user_id === null ? $participant->first_name : null;
            $match->player_one_last_name = $participant->user_id === null ? $participant->last_name : null;

            return;
        }

        $match->player_two_id = $participant->user_id;
        $match->player_two_first_name = $participant->user_id === null ? $participant->first_name : null;
        $match->player_two_last_name = $participant->user_id === null ? $participant->last_name : null;
    }

    /**
     * @param  1|2  $winnerSlot
     */
    public function advanceWinner(LeagueMatch $match, int $winnerSlot): void
    {
        if ($match->next_match_id === null || $match->next_match_slot === null) {
            return;
        }

        $nextMatch = LeagueMatch::query()->find($match->next_match_id);

        if ($nextMatch === null) {
            return;
        }

        $identity = $winnerSlot === 1
            ? $match->playerOneIdentity()
            : $match->playerTwoIdentity();

        if ($match->next_match_slot === 1) {
            $nextMatch->forceFill([
                'player_one_id' => $identity['user_id'],
                'player_one_first_name' => $identity['first_name'],
                'player_one_last_name' => $identity['last_name'],
            ])->save();
        } else {
            $nextMatch->forceFill([
                'player_two_id' => $identity['user_id'],
                'player_two_first_name' => $identity['first_name'],
                'player_two_last_name' => $identity['last_name'],
            ])->save();
        }

        $this->resolveVacantOpponentBye($nextMatch);
    }

    /**
     * If one player is present and the other bracket feeder is an empty slot,
     * auto-advance that player as a bye.
     */
    public function resolveVacantOpponentBye(LeagueMatch $match): void
    {
        if ($match->is_bye || $match->status === LeagueMatchStatus::Played) {
            return;
        }

        $hasOne = $match->hasPlayerOne();
        $hasTwo = $match->hasPlayerTwo();

        if ($hasOne === $hasTwo) {
            return;
        }

        $missingSlot = $hasOne ? 2 : 1;

        $feeder = LeagueMatch::query()
            ->where('next_match_id', $match->id)
            ->where('next_match_slot', $missingSlot)
            ->first();

        if ($feeder === null || ! $feeder->isEmptyBracketSlot()) {
            return;
        }

        $match->forceFill([
            'is_bye' => true,
            'status' => LeagueMatchStatus::Played->value,
            'played_at' => now(),
        ])->save();

        $this->advanceWinner($match, $hasOne ? 1 : 2);
    }
}
