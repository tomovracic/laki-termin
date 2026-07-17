<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\League */
class LeagueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'format' => $this->format?->value ?? 'round_robin',
            'rounds' => $this->rounds,
            'sets_best_of' => $this->sets_best_of,
            'knockout_draw_mode' => $this->knockout_draw_mode?->value,
            'created_by' => $this->created_by,
            'participants_count' => $this->whenCounted('participants'),
            'matches_count' => $this->whenCounted('matches'),
            'played_matches_count' => $this->when(
                isset($this->played_matches_count),
                fn () => $this->played_matches_count,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
