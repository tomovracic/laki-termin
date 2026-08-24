<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LeagueMatch */
class LeagueMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'league_id' => $this->league_id,
            'round' => $this->round,
            'bracket_round' => $this->bracket_round,
            'bracket_position' => $this->bracket_position,
            'next_match_id' => $this->next_match_id,
            'next_match_slot' => $this->next_match_slot,
            'is_bye' => (bool) $this->is_bye,
            'is_empty' => $this->isEmptyBracketSlot(),
            'status' => $this->status->value,
            'player_one' => $this->formatPlayer(
                $this->player_one_id,
                $this->playerOneDisplayName(),
                $this->relationLoaded('playerOne') ? $this->playerOne : null,
                $this->player_one_first_name,
                $this->player_one_last_name,
                $this->player_one_partner_id,
            ),
            'player_two' => $this->formatPlayer(
                $this->player_two_id,
                $this->playerTwoDisplayName(),
                $this->relationLoaded('playerTwo') ? $this->playerTwo : null,
                $this->player_two_first_name,
                $this->player_two_last_name,
                $this->player_two_partner_id,
            ),
            'set1_player_one_games' => $this->set1_player_one_games,
            'set1_player_two_games' => $this->set1_player_two_games,
            'set2_player_one_games' => $this->set2_player_one_games,
            'set2_player_two_games' => $this->set2_player_two_games,
            'set3_player_one_games' => $this->set3_player_one_games,
            'set3_player_two_games' => $this->set3_player_two_games,
            'set4_player_one_games' => $this->set4_player_one_games,
            'set4_player_two_games' => $this->set4_player_two_games,
            'set5_player_one_games' => $this->set5_player_one_games,
            'set5_player_two_games' => $this->set5_player_two_games,
            'played_at' => $this->played_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int|null, partner_id: int|null, name: string, first_name: string, last_name: string, avatar?: string|null}|null
     */
    private function formatPlayer(
        ?int $userId,
        string $displayName,
        mixed $user,
        ?string $firstName,
        ?string $lastName,
        ?int $partnerId = null,
    ): ?array {
        if ($userId === null && ($firstName === null || $lastName === null) && $displayName === '') {
            return null;
        }

        return [
            'id' => $userId,
            'partner_id' => $partnerId,
            'name' => $displayName !== '' ? $displayName : trim(($firstName ?? '').' '.($lastName ?? '')),
            'first_name' => $user?->first_name ?? $firstName ?? '',
            'last_name' => $user?->last_name ?? $lastName ?? '',
            'avatar' => $user?->avatar ?? null,
        ];
    }
}
