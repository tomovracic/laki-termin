<?php

declare(strict_types=1);

namespace App\Services\Leagues;

use App\DTO\Leagues\LeagueStandingsEntryData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\LeagueParticipant;
use Illuminate\Support\Collection;

class LeagueStandingsService
{
    /**
     * @return list<LeagueStandingsEntryData>
     */
    public function build(League $league, ?int $groupId = null): array
    {
        $participantsQuery = $league->participants()->with(['user', 'group']);

        if ($groupId !== null) {
            $participantsQuery->where('league_group_id', $groupId);
        }

        $participants = $participantsQuery->get();

        $playedMatches = $league->matches()
            ->played()
            ->when($groupId !== null, fn ($query) => $query->where('league_group_id', $groupId))
            ->when($groupId === null && $league->isGroupKnockout(), fn ($query) => $query->whereNotNull('league_group_id'))
            ->get();

        return $this->buildFrom($participants, $playedMatches);
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $participants
     * @param  Collection<int, LeagueMatch>  $playedMatches
     * @return list<LeagueStandingsEntryData>
     */
    public function buildFrom(Collection $participants, Collection $playedMatches): array
    {
        $stats = [];
        $participantsById = $participants->keyBy('id');
        $participantsByUserId = $participants
            ->filter(fn (LeagueParticipant $participant): bool => $participant->user_id !== null)
            ->keyBy('user_id');

        foreach ($participants as $participant) {
            $stats[$participant->id] = [
                'participant' => $participant,
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'sets_won' => 0,
                'sets_lost' => 0,
                'games_won' => 0,
                'games_lost' => 0,
            ];
        }

        foreach ($playedMatches as $match) {
            if ($match->is_bye || $match->isEmptyBracketSlot()) {
                continue;
            }

            $this->applyMatchToStats($stats, $match, $participantsById, $participantsByUserId);
        }

        $entries = array_map(function (array $row): LeagueStandingsEntryData {
            /** @var LeagueParticipant $participant */
            $participant = $row['participant'];
            $user = $participant->user;

            return new LeagueStandingsEntryData(
                participantId: $participant->id,
                userId: $participant->user_id,
                firstName: $user?->first_name ?? $participant->first_name ?? '',
                lastName: $user?->last_name ?? $participant->last_name ?? '',
                name: $participant->displayName(),
                matchesPlayed: $row['matches_played'],
                wins: $row['wins'],
                losses: $row['losses'],
                setsWon: $row['sets_won'],
                setsLost: $row['sets_lost'],
                setDifference: $row['sets_won'] - $row['sets_lost'],
                gamesWon: $row['games_won'],
                gamesLost: $row['games_lost'],
                gameDifference: $row['games_won'] - $row['games_lost'],
                groupId: $participant->league_group_id,
                groupName: $participant->group?->name,
            );
        }, array_values($stats));

        usort($entries, $this->compareEntries(...));

        return $entries;
    }

    public function compareEntries(LeagueStandingsEntryData $a, LeagueStandingsEntryData $b): int
    {
        if ($a->wins !== $b->wins) {
            return $b->wins <=> $a->wins;
        }

        if ($a->setDifference !== $b->setDifference) {
            return $b->setDifference <=> $a->setDifference;
        }

        if ($a->gameDifference !== $b->gameDifference) {
            return $b->gameDifference <=> $a->gameDifference;
        }

        if ($a->setsWon !== $b->setsWon) {
            return $b->setsWon <=> $a->setsWon;
        }

        return strcasecmp($a->firstName, $b->firstName);
    }

    /**
     * @param  array<int, array{participant: LeagueParticipant, matches_played: int, wins: int, losses: int, sets_won: int, sets_lost: int, games_won: int, games_lost: int}>  $stats
     * @param  Collection<int, LeagueParticipant>  $participantsById
     * @param  Collection<int, LeagueParticipant>  $participantsByUserId
     */
    private function applyMatchToStats(
        array &$stats,
        LeagueMatch $match,
        Collection $participantsById,
        Collection $participantsByUserId,
    ): void {
        if ($match->status !== LeagueMatchStatus::Played) {
            return;
        }

        $playerOneId = $this->resolveParticipantId($match, 1, $participantsById, $participantsByUserId);
        $playerTwoId = $this->resolveParticipantId($match, 2, $participantsById, $participantsByUserId);

        if ($playerOneId === null || $playerTwoId === null || ! isset($stats[$playerOneId], $stats[$playerTwoId])) {
            return;
        }

        $setCounts = $match->setCounts();
        $gameCounts = $match->gameCounts();

        $stats[$playerOneId]['matches_played']++;
        $stats[$playerTwoId]['matches_played']++;
        $stats[$playerOneId]['sets_won'] += $setCounts['player_one'];
        $stats[$playerOneId]['sets_lost'] += $setCounts['player_two'];
        $stats[$playerTwoId]['sets_won'] += $setCounts['player_two'];
        $stats[$playerTwoId]['sets_lost'] += $setCounts['player_one'];
        $stats[$playerOneId]['games_won'] += $gameCounts['player_one'];
        $stats[$playerOneId]['games_lost'] += $gameCounts['player_two'];
        $stats[$playerTwoId]['games_won'] += $gameCounts['player_two'];
        $stats[$playerTwoId]['games_lost'] += $gameCounts['player_one'];

        if ($setCounts['player_one'] > $setCounts['player_two']) {
            $stats[$playerOneId]['wins']++;
            $stats[$playerTwoId]['losses']++;
        } elseif ($setCounts['player_two'] > $setCounts['player_one']) {
            $stats[$playerTwoId]['wins']++;
            $stats[$playerOneId]['losses']++;
        }
    }

    /**
     * @param  Collection<int, LeagueParticipant>  $participantsById
     * @param  Collection<int, LeagueParticipant>  $participantsByUserId
     */
    private function resolveParticipantId(
        LeagueMatch $match,
        int $slot,
        Collection $participantsById,
        Collection $participantsByUserId,
    ): ?int {
        $participantId = $slot === 1
            ? $match->player_one_participant_id
            : $match->player_two_participant_id;

        if ($participantId !== null && $participantsById->has($participantId)) {
            return $participantId;
        }

        $userId = $slot === 1 ? $match->player_one_id : $match->player_two_id;

        if ($userId !== null && $participantsByUserId->has($userId)) {
            return $participantsByUserId->get($userId)->id;
        }

        return null;
    }
}
