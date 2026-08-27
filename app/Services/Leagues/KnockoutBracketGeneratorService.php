<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\Enums\KnockoutDrawMode;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;
use Illuminate\Support\Collection;

class KnockoutBracketGeneratorService
{
    public function __construct(
        protected LeagueMatchResultValidator $resultValidator,
    ) {}

    /**
     * @param  list<LeagueParticipant>  $participants  ordered by seed (index 0 = seed 1)
     */
    public function generate(League $league, array $participants): void
    {
        $this->generateRound($league, 1, $participants);
    }

    /**
     * After the current round is explicitly finished: if complete and not terminal,
     * generate the next round from winners (+ bye recipient).
     */
    public function tryGenerateNextRound(League $league): void
    {
        $league->loadMissing(['matches', 'participants.user']);

        $currentRound = (int) $league->matches->max('bracket_round');

        if ($currentRound < 1) {
            return;
        }

        $roundMatches = $league->matches->where('bracket_round', $currentRound);

        if (! $this->isRoundComplete($roundMatches)) {
            return;
        }

        if ($this->isTerminalRound($roundMatches)) {
            return;
        }

        $advancers = $this->collectAdvancers($roundMatches, $league->participants);

        if (count($advancers) < 2) {
            return;
        }

        $this->generateRound($league, $currentRound + 1, $advancers);
    }

    /**
     * @param  list<LeagueParticipant>|Collection<int, LeagueParticipant>  $participants
     */
    public function generateRound(League $league, int $round, iterable $participants): void
    {
        $players = collect($participants)->values();
        $count = $players->count();

        if ($count < 2) {
            return;
        }

        if ($count === 3) {
            $this->createFinalThreeRoundRobin($league, $round, $players);

            return;
        }

        if ($count === 2) {
            $this->createMatch($league, $round, 0, $players[0], $players[1]);

            return;
        }

        $drawMode = $league->knockout_draw_mode ?? KnockoutDrawMode::Seeded;
        $ordered = $this->orderParticipantsForPairing($players, $drawMode);

        $byeRecipient = null;

        if ($count % 2 === 1) {
            $byeRecipient = $this->pickByeRecipient($ordered, $drawMode);
            $ordered = $ordered
                ->reject(fn (LeagueParticipant $p): bool => $p->id === $byeRecipient->id)
                ->values();
        }

        $position = 0;

        if ($byeRecipient !== null) {
            $this->createByeMatch($league, $round, $position, $byeRecipient);
            $position++;
        }

        $pairs = $this->buildPairs($ordered, $drawMode);

        foreach ($pairs as $pair) {
            $this->createMatch($league, $round, $position, $pair[0], $pair[1]);
            $position++;
        }
    }

    /**
     * @return array{id: int|null, name: string, user_id: int|null, participant_id: int|null}|null
     */
    public function resolveChampion(League $league): ?array
    {
        $league->loadMissing([
            'matches.playerOne',
            'matches.playerTwo',
            'matches.playerOnePartner',
            'matches.playerTwoPartner',
            'participants.user',
            'participants.partner',
        ]);

        $currentRound = (int) $league->matches->max('bracket_round');

        if ($currentRound < 1) {
            return null;
        }

        $roundMatches = $league->matches->where('bracket_round', $currentRound);

        if (! $this->isRoundComplete($roundMatches) || ! $this->isTerminalRound($roundMatches)) {
            return null;
        }

        if ($this->isFinalThreeRoundRobin($roundMatches)) {
            return $this->championFromRoundRobin($roundMatches);
        }

        $final = $roundMatches->first(fn (LeagueMatch $match): bool => ! $match->is_bye);

        if ($final === null || $final->status !== LeagueMatchStatus::Played) {
            return null;
        }

        $winnerSlot = $this->resultValidator->winnerSlot($final);
        $participantId = $winnerSlot === 1
            ? $final->player_one_participant_id
            : $final->player_two_participant_id;
        $userId = $winnerSlot === 1 ? $final->player_one_id : $final->player_two_id;

        $name = $winnerSlot === 1
            ? $final->playerOneDisplayName()
            : $final->playerTwoDisplayName();

        if ($participantId === null && $userId === null) {
            return null;
        }

        return [
            'id' => $participantId ?? $userId,
            'user_id' => $userId,
            'participant_id' => $participantId,
            'name' => $name,
        ];
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     */
    public function isRoundComplete(Collection $roundMatches): bool
    {
        if ($roundMatches->isEmpty()) {
            return false;
        }

        return $roundMatches
            ->filter(fn (LeagueMatch $match): bool => ! $match->is_bye)
            ->every(fn (LeagueMatch $match): bool => $match->status === LeagueMatchStatus::Played);
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     */
    public function isTerminalRound(Collection $roundMatches): bool
    {
        if ($this->isFinalThreeRoundRobin($roundMatches)) {
            return true;
        }

        $competitive = $roundMatches->filter(fn (LeagueMatch $match): bool => ! $match->is_bye);

        return $competitive->count() === 1
            && $this->uniquePlayerCount($roundMatches) === 2;
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     */
    public function isFinalThreeRoundRobin(Collection $roundMatches): bool
    {
        $competitive = $roundMatches->filter(fn (LeagueMatch $match): bool => ! $match->is_bye);

        return $competitive->count() === 3
            && $this->uniquePlayerCount($roundMatches) === 3;
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $participants
     * @return Collection<int, LeagueParticipant>
     */
    private function orderParticipantsForPairing(Collection $participants, KnockoutDrawMode $drawMode): Collection
    {
        if ($drawMode === KnockoutDrawMode::Random) {
            return $participants->shuffle()->values();
        }

        return $participants->sortBy(fn (LeagueParticipant $p): int => $p->seed ?? PHP_INT_MAX)->values();
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $participants
     * @return list<array{0: LeagueParticipant, 1: LeagueParticipant}>
     */
    private function buildPairs(Collection $participants, KnockoutDrawMode $drawMode): array
    {
        $count = $participants->count();
        $pairCount = intdiv($count, 2);
        $pairs = [];

        if ($drawMode === KnockoutDrawMode::Random) {
            for ($i = 0; $i < $pairCount; $i++) {
                $pairs[] = [$participants[$i * 2], $participants[$i * 2 + 1]];
            }

            return $pairs;
        }

        for ($i = 0; $i < $pairCount; $i++) {
            $pairs[] = [$participants[$i], $participants[$count - 1 - $i]];
        }

        return $pairs;
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $participants
     */
    private function pickByeRecipient(Collection $participants, KnockoutDrawMode $drawMode): LeagueParticipant
    {
        if ($drawMode === KnockoutDrawMode::Random) {
            return $participants->random();
        }

        $neverHadBye = $participants
            ->filter(fn (LeagueParticipant $p): bool => ! $p->received_bye)
            ->sortBy(fn (LeagueParticipant $p): int => $p->seed ?? PHP_INT_MAX)
            ->values();

        if ($neverHadBye->isNotEmpty()) {
            return $neverHadBye->first();
        }

        return $participants
            ->sortBy(fn (LeagueParticipant $p): int => $p->seed ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $players
     */
    private function createFinalThreeRoundRobin(League $league, int $round, Collection $players): void
    {
        $a = $players[0];
        $b = $players[1];
        $c = $players[2];

        $this->createMatch($league, $round, 0, $a, $b);
        $this->createMatch($league, $round, 1, $a, $c);
        $this->createMatch($league, $round, 2, $b, $c);
    }

    private function createMatch(
        League $league,
        int $round,
        int $position,
        LeagueParticipant $playerOne,
        LeagueParticipant $playerTwo,
    ): LeagueMatch {
        $match = LeagueMatch::query()->create([
            'league_id' => $league->id,
            'player_one_id' => null,
            'player_two_id' => null,
            'round' => $round,
            'bracket_round' => $round,
            'bracket_position' => $position,
            'next_match_id' => null,
            'next_match_slot' => null,
            'is_bye' => false,
            'status' => LeagueMatchStatus::Pending->value,
        ]);

        $this->assignParticipantToSlot($match, 1, $playerOne);
        $this->assignParticipantToSlot($match, 2, $playerTwo);
        $match->save();

        return $match;
    }

    private function createByeMatch(
        League $league,
        int $round,
        int $position,
        LeagueParticipant $participant,
    ): LeagueMatch {
        $match = LeagueMatch::query()->create([
            'league_id' => $league->id,
            'player_one_id' => null,
            'player_two_id' => null,
            'round' => $round,
            'bracket_round' => $round,
            'bracket_position' => $position,
            'next_match_id' => null,
            'next_match_slot' => null,
            'is_bye' => true,
            'status' => LeagueMatchStatus::Played->value,
            'played_at' => now(),
        ]);

        $this->assignParticipantToSlot($match, 1, $participant);
        $match->save();

        $participant->forceFill(['received_bye' => true])->save();

        return $match;
    }

    private function assignParticipantToSlot(LeagueMatch $match, int $slot, LeagueParticipant $participant): void
    {
        if ($slot === 1) {
            $match->player_one_id = $participant->user_id;
            $match->player_one_first_name = $participant->user_id === null ? $participant->first_name : null;
            $match->player_one_last_name = $participant->user_id === null ? $participant->last_name : null;
            $match->player_one_partner_id = $participant->partner_user_id;
            $match->player_one_participant_id = $participant->id;

            return;
        }

        $match->player_two_id = $participant->user_id;
        $match->player_two_first_name = $participant->user_id === null ? $participant->first_name : null;
        $match->player_two_last_name = $participant->user_id === null ? $participant->last_name : null;
        $match->player_two_partner_id = $participant->partner_user_id;
        $match->player_two_participant_id = $participant->id;
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     * @param  Collection<int, LeagueParticipant>  $allParticipants
     * @return list<LeagueParticipant>
     */
    private function collectAdvancers(Collection $roundMatches, Collection $allParticipants): array
    {
        $advancers = [];

        foreach ($roundMatches as $match) {
            if ($match->is_bye) {
                $participant = $this->participantFromSlot(
                    $match,
                    $match->player_one_participant_id !== null || $match->hasPlayerOne() ? 1 : 2,
                    $allParticipants,
                );

                if ($participant !== null) {
                    $advancers[] = $participant;
                }

                continue;
            }

            $winnerSlot = $this->resultValidator->winnerSlot($match);
            $participant = $this->participantFromSlot($match, $winnerSlot, $allParticipants);

            if ($participant !== null) {
                $advancers[] = $participant;
            }
        }

        return $advancers;
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $allParticipants
     */
    private function participantFromSlot(LeagueMatch $match, int $slot, Collection $allParticipants): ?LeagueParticipant
    {
        $participantId = $slot === 1
            ? $match->player_one_participant_id
            : $match->player_two_participant_id;

        if ($participantId !== null) {
            return $allParticipants->first(
                fn (LeagueParticipant $participant): bool => $participant->id === $participantId,
            );
        }

        $userId = $slot === 1 ? $match->player_one_id : $match->player_two_id;

        if ($userId === null) {
            $firstName = $slot === 1 ? $match->player_one_first_name : $match->player_two_first_name;
            $lastName = $slot === 1 ? $match->player_one_last_name : $match->player_two_last_name;

            return $allParticipants->first(
                fn (LeagueParticipant $participant): bool => $participant->user_id === null
                    && $participant->first_name === $firstName
                    && $participant->last_name === $lastName,
            );
        }

        return $allParticipants->first(
            fn (LeagueParticipant $participant): bool => $participant->user_id === $userId,
        );
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     */
    private function uniquePlayerCount(Collection $roundMatches): int
    {
        $ids = [];

        foreach ($roundMatches as $match) {
            foreach ([1, 2] as $slot) {
                $key = $this->playerIdentityKey($match, $slot);

                if ($key !== null) {
                    $ids[$key] = true;
                }
            }
        }

        return count($ids);
    }

    private function playerIdentityKey(LeagueMatch $match, int $slot): ?string
    {
        $participantId = $slot === 1
            ? $match->player_one_participant_id
            : $match->player_two_participant_id;

        if ($participantId !== null) {
            return 'p:'.$participantId;
        }

        $userId = $slot === 1 ? $match->player_one_id : $match->player_two_id;

        if ($userId !== null) {
            return 'u:'.$userId;
        }

        $firstName = $slot === 1 ? $match->player_one_first_name : $match->player_two_first_name;
        $lastName = $slot === 1 ? $match->player_one_last_name : $match->player_two_last_name;

        if ($firstName === null && $lastName === null) {
            return null;
        }

        return 'g:'.($firstName ?? '').'|'.($lastName ?? '');
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     * @return array{id: int|null, name: string, user_id: int|null, participant_id: int|null}|null
     */
    private function championFromRoundRobin(Collection $roundMatches): ?array
    {
        $stats = [];

        foreach ($roundMatches as $match) {
            if ($match->is_bye || $match->status !== LeagueMatchStatus::Played) {
                continue;
            }

            $playerOneKey = $this->playerIdentityKey($match, 1);
            $playerTwoKey = $this->playerIdentityKey($match, 2);

            if ($playerOneKey === null || $playerTwoKey === null) {
                continue;
            }

            foreach ([$playerOneKey, $playerTwoKey] as $key) {
                if (! isset($stats[$key])) {
                    $stats[$key] = [
                        'wins' => 0,
                        'sets_won' => 0,
                        'sets_lost' => 0,
                        'games_won' => 0,
                        'games_lost' => 0,
                        'name' => '',
                        'user_id' => null,
                        'participant_id' => null,
                    ];
                }
            }

            $stats[$playerOneKey]['user_id'] = $match->player_one_id;
            $stats[$playerOneKey]['participant_id'] = $match->player_one_participant_id;
            $stats[$playerTwoKey]['user_id'] = $match->player_two_id;
            $stats[$playerTwoKey]['participant_id'] = $match->player_two_participant_id;

            $winnerSlot = $this->resultValidator->winnerSlot($match);
            $winnerKey = $winnerSlot === 1 ? $playerOneKey : $playerTwoKey;
            $stats[$winnerKey]['wins']++;

            $setDiff = $match->setCounts();
            $stats[$playerOneKey]['sets_won'] += $setDiff['player_one'];
            $stats[$playerOneKey]['sets_lost'] += $setDiff['player_two'];
            $stats[$playerTwoKey]['sets_won'] += $setDiff['player_two'];
            $stats[$playerTwoKey]['sets_lost'] += $setDiff['player_one'];

            $gameDiff = $match->gameCounts();
            $stats[$playerOneKey]['games_won'] += $gameDiff['player_one'];
            $stats[$playerOneKey]['games_lost'] += $gameDiff['player_two'];
            $stats[$playerTwoKey]['games_won'] += $gameDiff['player_two'];
            $stats[$playerTwoKey]['games_lost'] += $gameDiff['player_one'];

            $stats[$playerOneKey]['name'] = $match->playerOneDisplayName();
            $stats[$playerTwoKey]['name'] = $match->playerTwoDisplayName();
        }

        if ($stats === []) {
            return null;
        }

        // Tiebreakers (final three): wins → set difference → sets won → game difference → games won.
        uasort($stats, function (array $a, array $b): int {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            $aSetDiff = $a['sets_won'] - $a['sets_lost'];
            $bSetDiff = $b['sets_won'] - $b['sets_lost'];

            if ($aSetDiff !== $bSetDiff) {
                return $bSetDiff <=> $aSetDiff;
            }

            if ($a['sets_won'] !== $b['sets_won']) {
                return $b['sets_won'] <=> $a['sets_won'];
            }

            $aGameDiff = $a['games_won'] - $a['games_lost'];
            $bGameDiff = $b['games_won'] - $b['games_lost'];

            if ($aGameDiff !== $bGameDiff) {
                return $bGameDiff <=> $aGameDiff;
            }

            return $b['games_won'] <=> $a['games_won'];
        });

        $winnerKey = (string) array_key_first($stats);
        $winner = $stats[$winnerKey];
        $participantId = $winner['participant_id'] !== null ? (int) $winner['participant_id'] : null;
        $userId = $winner['user_id'] !== null ? (int) $winner['user_id'] : null;

        return [
            'id' => $participantId ?? $userId,
            'user_id' => $userId,
            'participant_id' => $participantId,
            'name' => $winner['name'],
        ];
    }
}
