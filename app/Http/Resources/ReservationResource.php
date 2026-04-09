<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reservation */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status?->value,
            'display_status' => $this->resolveDisplayStatus(),
            'reserved_for_date' => $this->reserved_for_date?->toDateString(),
            'reserved_from_time' => $this->reserved_from_time,
            'reserved_to_time' => $this->reserved_to_time,
            'confirmed_at' => $this->confirmed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'slot' => ReservationSlotResource::make($this->whenLoaded('slot')),
            'tokens' => ReservationTokenResource::collection($this->whenLoaded('tokens')),
        ];
    }

    private function resolveDisplayStatus(): string
    {
        if ($this->status === ReservationStatus::Cancelled) {
            return ReservationStatus::Cancelled->value;
        }

        $reservationEndedAt = $this->slot?->ends_at;

        if ($reservationEndedAt !== null && $reservationEndedAt->isPast()) {
            return 'played';
        }

        return ReservationStatus::Pending->value;
    }
}
