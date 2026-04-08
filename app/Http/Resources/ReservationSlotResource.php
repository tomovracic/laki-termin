<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationSlotStatus;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReservationSlot */
class ReservationSlotResource extends JsonResource
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwnedByCurrentUser = $this->status === ReservationSlotStatus::Reserved
            && $this->reservation !== null
            && $request->user() !== null
            && $this->reservation->user_id === $request->user()->id;

        $globalCancellationCutoffHours = $request->attributes->get('global_cancellation_cutoff_hours');

        if (! is_int($globalCancellationCutoffHours)) {
            $globalCancellationCutoffHours = TerrainSetting::query()
                ->global()
                ->value('cancellation_cutoff_hours') ?? 0;
            $request->attributes->set('global_cancellation_cutoff_hours', $globalCancellationCutoffHours);
        }

        $canCancel = false;

        if ($isOwnedByCurrentUser && $this->starts_at !== null) {
            $now = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
            $slotStart = CarbonImmutable::parse(
                $this->starts_at->toDateTimeString(),
                self::BUSINESS_TIMEZONE,
            );

            $canCancel = $slotStart->isFuture()
                && ($globalCancellationCutoffHours <= 0
                    || $now->addHours($globalCancellationCutoffHours)->lessThan($slotStart));
        }

        return [
            'id' => $this->id,
            'terrain_id' => $this->terrain_id,
            'terrain' => TerrainResource::make($this->whenLoaded('terrain')),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status?->value,
            'reservation_id_for_current_user' => $this->when(
                $isOwnedByCurrentUser,
                fn (): int => $this->reservation->id,
            ),
            'can_cancel' => $canCancel,
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
