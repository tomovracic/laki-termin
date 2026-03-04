<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Terrain */
class DashboardTerrainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'available_slots_count' => $this->reservation_slots_count ?? $this->whenCounted('reservationSlots'),
            'slots' => ReservationSlotResource::collection(
                $this->whenLoaded('reservationSlots')
            )->resolve($request),
        ];
    }
}
