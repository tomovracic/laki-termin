<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Group */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color instanceof \BackedEnum ? $this->color->value : $this->color,
            'color_hex' => $this->color instanceof \App\Enums\GroupColor
                ? $this->color->hex()
                : null,
            'can_access_ranking' => (bool) $this->can_access_ranking,
            'can_view_all_ranking_groups' => (bool) $this->can_view_all_ranking_groups,
            'can_access_match_history' => (bool) $this->can_access_match_history,
            'can_view_all_match_history_groups' => (bool) $this->can_view_all_match_history_groups,
            'created_at' => $this->created_at,
        ];
    }
}
