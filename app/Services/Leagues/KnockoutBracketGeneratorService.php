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
     * @return array{id: int, name: string, user_id: int}|null
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
        $userId = $winnerSlot === 1 ? $final->player_one_id : $final->player_two_id;

        if ($userId === null) {
            return null;
        }

        $name = $winnerSlot === 1
            ? $final->playerOneDisplayName()
            : $final->playerTwoDisplayName();

        return [
            'id' => $userId,
            'user_id' => $userId,
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

            return;
        }

        $match->player_two_id = $participant->user_id;
        $match->player_two_first_name = $participant->user_id === null ? $participant->first_name : null;
        $match->player_two_last_name = $participant->user_id === null ? $participant->last_name : null;
        $match->player_two_partner_id = $participant->partner_user_id;
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     * @param  Collection<int, LeagueParticipant>  $allParticipants
     * @return list<LeagueParticipant>
     */
    private function collectAdvancers(Collection $roundMatches, Collection $allParticipants): array
    {
        $byUserId = $allParticipants->keyBy('user_id');
        $advancers = [];

        foreach ($roundMatches as $match) {
            if ($match->is_bye) {
                $userId = $match->player_one_id ?? $match->player_two_id;

                if ($userId !== null && $byUserId->has($userId)) {
                    $advancers[] = $byUserId->get($userId);
                }

                continue;
            }

            $winnerSlot = $this->resultValidator->winnerSlot($match);
            $userId = $winnerSlot === 1 ? $match->player_one_id : $match->player_two_id;

            if ($userId !== null && $byUserId->has($userId)) {
                $advancers[] = $byUserId->get($userId);
            }
        }

        return $advancers;
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     */
    private function uniquePlayerCount(Collection $roundMatches): int
    {
        $ids = [];

        foreach ($roundMatches as $match) {
            if ($match->player_one_id !== null) {
                $ids[$match->player_one_id] = true;
            }

            if ($match->player_two_id !== null) {
                $ids[$match->player_two_id] = true;
            }
        }

        return count($ids);
    }

    /**
     * @param  Collection<int, LeagueMatch>  $roundMatches
     * @return array{id: int, name: string, user_id: int}|null
     */
    private function championFromRoundRobin(Collection $roundMatches): ?array
    {
        $stats = [];

        foreach ($roundMatches as $match) {
            if ($match->is_bye || $match->status !== LeagueMatchStatus::Played) {
                continue;
            }

            $playerOneId = $match->player_one_id;
            $playerTwoId = $match->player_two_id;

            if ($playerOneId === null || $playerTwoId === null) {
                continue;
            }

            foreach ([$playerOneId, $playerTwoId] as $userId) {
                if (! isset($stats[$userId])) {
                    $stats[$userId] = [
                        'wins' => 0,
                        'sets_won' => 0,
                        'sets_lost' => 0,
                        'games_won' => 0,
                        'games_lost' => 0,
                        'name' => '',
                    ];
                }
            }

            $winnerSlot = $this->resultValidator->winnerSlot($match);
            $winnerId = $winnerSlot === 1 ? $playerOneId : $playerTwoId;
            $stats[$winnerId]['wins']++;

            $setDiff = $this->setCounts($match);
            $stats[$playerOneId]['sets_won'] += $setDiff['player_one'];
            $stats[$playerOneId]['sets_lost'] += $setDiff['player_two'];
            $stats[$playerTwoId]['sets_won'] += $setDiff['player_two'];
            $stats[$playerTwoId]['sets_lost'] += $setDiff['player_one'];

            $gameDiff = $this->gameCounts($match);
            $stats[$playerOneId]['games_won'] += $gameDiff['player_one'];
            $stats[$playerOneId]['games_lost'] += $gameDiff['player_two'];
            $stats[$playerTwoId]['games_won'] += $gameDiff['player_two'];
            $stats[$playerTwoId]['games_lost'] += $gameDiff['player_one'];

            $stats[$playerOneId]['name'] = $match->playerOneDisplayName();
            $stats[$playerTwoId]['name'] = $match->playerTwoDisplayName();
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

        $winnerId = (int) array_key_first($stats);

        return [
            'id' => $winnerId,
            'user_id' => $winnerId,
            'name' => $stats[$winnerId]['name'],
        ];
    }

    /**
     * @return array{player_one: int, player_two: int}
     */
    private function setCounts(LeagueMatch $match): array
    {
        $playerOne = 0;
        $playerTwo = 0;

        $sets = [
            [$match->set1_player_one_games, $match->set1_player_two_games],
            [$match->set2_player_one_games, $match->set2_player_two_games],
            [$match->set3_player_one_games, $match->set3_player_two_games],
            [$match->set4_player_one_games, $match->set4_player_two_games],
            [$match->set5_player_one_games, $match->set5_player_two_games],
        ];

        foreach ($sets as [$one, $two]) {
            if ($one === null || $two === null) {
                continue;
            }

            if ($one > $two) {
                $playerOne++;
            } elseif ($two > $one) {
                $playerTwo++;
            }
        }

        return ['player_one' => $playerOne, 'player_two' => $playerTwo];
    }

    /**
     * @return array{player_one: int, player_two: int}
     */
    private function gameCounts(LeagueMatch $match): array
    {
        $playerOne = 0;
        $playerTwo = 0;

        $sets = [
            [$match->set1_player_one_games, $match->set1_player_two_games],
            [$match->set2_player_one_games, $match->set2_player_two_games],
            [$match->set3_player_one_games, $match->set3_player_two_games],
            [$match->set4_player_one_games, $match->set4_player_two_games],
            [$match->set5_player_one_games, $match->set5_player_two_games],
        ];

        foreach ($sets as [$one, $two]) {
            if ($one === null || $two === null) {
                continue;
            }

            $playerOne += $one;
            $playerTwo += $two;
        }

        return ['player_one' => $playerOne, 'player_two' => $playerTwo];
    }
}
