<?php

declare(strict_types=1);

namespace App\Actions\Leagues;

use App\DTO\Leagues\LeagueStandingsEntryData;
use App\Enums\LeagueMatchStatus;
use App\Models\League;
use App\Models\LeagueMatch;
use App\Models\User;
use App\Services\Leagues\GroupQualificationService;
use App\Services\Leagues\KnockoutBracketGeneratorService;
use App\Services\Leagues\LeagueStandingsService;

class BuildLeaguePageDataAction
{
    public function __construct(
        protected LeagueStandingsService $standingsService,
        protected KnockoutBracketGeneratorService $bracketGenerator,
        protected GroupQualificationService $qualificationService,
    ) {}

    /**
     * @return array{
     *     league: array<string, mixed>,
     *     standings: list<array<string, mixed>>,
     *     matches: list<array<string, mixed>>,
     *     participants: list<array<string, mixed>>,
     *     groups: list<array<string, mixed>>,
     *     qualifiers: list<array<string, mixed>>,
     *     available_users: list<array<string, mixed>>,
     *     knockout_champion: array{id: int|null, user_id: int|null, participant_id: int|null, name: string}|null
     * }
     */
    public function execute(League $league, bool $includeAvailableUsers = false): array
    {
        $league->load([
            'groups.participants.user',
            'groups.participants.group',
            'participants.user',
            'participants.partner',
            'participants.group',
            'matches.playerOne',
            'matches.playerTwo',
            'matches.playerOnePartner',
            'matches.playerTwoPartner',
        ]);

        $standings = [];
        $groups = [];
        $qualifiers = [];
        $groupStageComplete = false;
        $canStartKnockout = false;

        if ($league->isGroupKnockout()) {
            foreach ($league->groups->sortBy('sort_order') as $group) {
                $groupStandings = $this->standingsService->build($league, $group->id);

                $groups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                    'standings' => array_map($this->formatStandingsEntry(...), $groupStandings),
                ];
            }

            $qualifiers = array_map($this->formatStandingsEntry(...), $this->qualificationService->qualify($league));
            $groupStageComplete = $this->qualificationService->isGroupStageComplete($league);
            $canStartKnockout = $league->isGroupStage() && $groupStageComplete;
        } elseif (! $league->isKnockout()) {
            $standings = array_map(
                $this->formatStandingsEntry(...),
                $this->standingsService->build($league),
            );
        }

        $participantIds = $league->participants
            ->flatMap(fn ($participant) => [$participant->user_id, $participant->partner_user_id])
            ->filter()
            ->all();

        $participants = $league->participants
            ->sortBy('seed')
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'partner_user_id' => $participant->partner_user_id,
                'league_group_id' => $participant->league_group_id,
                'name' => $participant->displayName(),
                'first_name' => $participant->user?->first_name ?? $participant->first_name ?? '',
                'last_name' => $participant->user?->last_name ?? $participant->last_name ?? '',
                'seed' => $participant->seed,
                'received_bye' => (bool) $participant->received_bye,
            ])
            ->values()
            ->all();

        $matches = $league->matches
            ->sortBy([
                ['bracket_round', 'asc'],
                ['bracket_position', 'asc'],
                ['status', 'asc'],
                ['round', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($match) => $this->formatMatch($match))
            ->values()
            ->all();

        $knockoutMatches = $league->matches->filter(
            fn (LeagueMatch $match): bool => $match->bracket_round !== null,
        );
        $currentBracketRound = (int) ($knockoutMatches->max('bracket_round') ?? 0);
        $currentRoundMatches = $knockoutMatches->where('bracket_round', $currentBracketRound);
        $inKnockoutStage = $league->isInKnockoutStage();
        $roundComplete = $inKnockoutStage
            && $this->bracketGenerator->isRoundComplete($currentRoundMatches);
        $isTerminalRound = $inKnockoutStage
            && $this->bracketGenerator->isTerminalRound($currentRoundMatches);
        $knockoutChampion = $inKnockoutStage
            ? $this->bracketGenerator->resolveChampion($league)
            : null;
        $canFinishRound = $inKnockoutStage
            && $roundComplete
            && ! $isTerminalRound
            && $currentBracketRound > 0
            && $knockoutChampion === null;

        $payload = [
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
                'format' => $league->format->value,
                'participant_mode' => $league->participant_mode->value,
                'rounds' => $league->rounds,
                'sets_best_of' => $league->sets_best_of,
                'knockout_draw_mode' => $league->knockout_draw_mode?->value,
                'qualify_per_group' => $league->qualify_per_group,
                'best_runners_up' => $league->best_runners_up,
                'current_stage' => $league->current_stage?->value,
                'participants_count' => count($participants),
                'matches_count' => count($matches),
                'played_matches_count' => $league->matches->where('status', LeagueMatchStatus::Played)->count(),
                'current_bracket_round' => $currentBracketRound > 0 ? $currentBracketRound : null,
                'next_round_pending' => $inKnockoutStage
                    && ! $roundComplete
                    && $currentBracketRound > 0
                    && $knockoutChampion === null,
                'can_finish_round' => $canFinishRound,
                'can_start_knockout' => $canStartKnockout,
                'group_stage_complete' => $groupStageComplete,
            ],
            'standings' => $standings,
            'matches' => $matches,
            'participants' => $participants,
            'groups' => $groups,
            'qualifiers' => $qualifiers,
            'available_users' => [],
            'knockout_champion' => $knockoutChampion,
        ];

        if ($includeAvailableUsers && ! $league->isKnockout() && ! $league->isGroupKnockout()) {
            $payload['available_users'] = User::query()
                ->whereNotIn('id', $participantIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ])
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStandingsEntry(LeagueStandingsEntryData $entry): array
    {
        return [
            'participant_id' => $entry->participantId,
            'user_id' => $entry->userId,
            'first_name' => $entry->firstName,
            'last_name' => $entry->lastName,
            'name' => $entry->name,
            'matches_played' => $entry->matchesPlayed,
            'wins' => $entry->wins,
            'losses' => $entry->losses,
            'sets_won' => $entry->setsWon,
            'sets_lost' => $entry->setsLost,
            'set_difference' => $entry->setDifference,
            'games_won' => $entry->gamesWon,
            'games_lost' => $entry->gamesLost,
            'game_difference' => $entry->gameDifference,
            'group_id' => $entry->groupId,
            'group_name' => $entry->groupName,
            'rank_in_group' => $entry->rankInGroup,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMatch(LeagueMatch $match): array
    {
        return [
            'id' => $match->id,
            'round' => $match->round,
            'league_group_id' => $match->league_group_id,
            'bracket_round' => $match->bracket_round,
            'bracket_position' => $match->bracket_position,
            'next_match_id' => $match->next_match_id,
            'next_match_slot' => $match->next_match_slot,
            'is_bye' => (bool) $match->is_bye,
            'is_empty' => $match->isEmptyBracketSlot(),
            'status' => $match->status->value,
            'player_one' => $this->formatMatchPlayer(
                $match->player_one_id,
                $match->playerOneDisplayName(),
                $match->playerOne,
                $match->player_one_first_name,
                $match->player_one_last_name,
                $match->player_one_partner_id,
                $match->player_one_participant_id,
            ),
            'player_two' => $this->formatMatchPlayer(
                $match->player_two_id,
                $match->playerTwoDisplayName(),
                $match->playerTwo,
                $match->player_two_first_name,
                $match->player_two_last_name,
                $match->player_two_partner_id,
                $match->player_two_participant_id,
            ),
            'set1_player_one_games' => $match->set1_player_one_games,
            'set1_player_two_games' => $match->set1_player_two_games,
            'set2_player_one_games' => $match->set2_player_one_games,
            'set2_player_two_games' => $match->set2_player_two_games,
            'set3_player_one_games' => $match->set3_player_one_games,
            'set3_player_two_games' => $match->set3_player_two_games,
            'set4_player_one_games' => $match->set4_player_one_games,
            'set4_player_two_games' => $match->set4_player_two_games,
            'set5_player_one_games' => $match->set5_player_one_games,
            'set5_player_two_games' => $match->set5_player_two_games,
            'played_at' => $match->played_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int|null, participant_id: int|null, partner_id: int|null, name: string, first_name: string, last_name: string}|null
     */
    private function formatMatchPlayer(
        ?int $userId,
        string $displayName,
        mixed $user,
        ?string $firstName,
        ?string $lastName,
        ?int $partnerId = null,
        ?int $participantId = null,
    ): ?array {
        if ($userId === null && $firstName === null && $lastName === null && $displayName === '') {
            return null;
        }

        return [
            'id' => $userId,
            'participant_id' => $participantId,
            'partner_id' => $partnerId,
            'name' => $displayName !== '' ? $displayName : trim(($firstName ?? '').' '.($lastName ?? '')),
            'first_name' => $user?->first_name ?? $firstName ?? '',
            'last_name' => $user?->last_name ?? $lastName ?? '',
        ];
    }
}
