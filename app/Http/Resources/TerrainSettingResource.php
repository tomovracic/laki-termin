<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TerrainSetting */
class TerrainSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'max_advance_days' => $this->max_advance_days,
            'cancellation_cutoff_hours' => $this->cancellation_cutoff_hours ?? 0,
            'availability_periods' => $this->availability_periods ?? [],
        ];
    }
}
