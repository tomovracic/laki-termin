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
            'status' => $this->status->value,
            'player_one' => UserResource::make($this->whenLoaded('playerOne')),
            'player_two' => UserResource::make($this->whenLoaded('playerTwo')),
            'set1_player_one_games' => $this->set1_player_one_games,
            'set1_player_two_games' => $this->set1_player_two_games,
            'set2_player_one_games' => $this->set2_player_one_games,
            'set2_player_two_games' => $this->set2_player_two_games,
            'set3_player_one_games' => $this->set3_player_one_games,
            'set3_player_two_games' => $this->set3_player_two_games,
            'played_at' => $this->played_at?->toIso8601String(),
        ];
    }
}
