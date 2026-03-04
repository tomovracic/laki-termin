<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationSlotStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReservationSlot */
class ReservationSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terrain_id' => $this->terrain_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status?->value,
            'reservation_id_for_current_user' => $this->when(
                $this->status === ReservationSlotStatus::Reserved
                && $this->reservation !== null
                && $request->user() !== null
                && $this->reservation->user_id === $request->user()->id,
                fn (): int => $this->reservation->id,
            ),
            'can_cancel' => $this->status === ReservationSlotStatus::Reserved
                && $this->reservation !== null
                && $request->user() !== null
                && $this->reservation->user_id === $request->user()->id
                && $this->starts_at !== null
                && $this->starts_at->isFuture(),
            'reserved_by' => $this->when(
                $this->status === ReservationSlotStatus::Reserved
                && $this->reservation?->user !== null,
                fn (): array => [
                    'first_name' => $this->reservation->user->first_name,
                    'last_name' => $this->reservation->user->last_name,
                ],
            ),
        ];
    }
}
