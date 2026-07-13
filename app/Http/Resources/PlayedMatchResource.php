<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlayedMatch */
class PlayedMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'player_one' => [
                'user_id' => $this->player_one_user_id,
                'name' => $this->playerOneDisplayName(),
            ],
            'player_two' => [
                'user_id' => $this->player_two_user_id,
                'name' => $this->playerTwoDisplayName(),
            ],
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
