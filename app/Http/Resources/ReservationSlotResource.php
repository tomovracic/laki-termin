<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ReservationSlotStatus;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\ReservationSlot */
class ReservationSlotResource extends JsonResource
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isReservationCancellableByCurrentUser = $this->status === ReservationSlotStatus::Reserved
            && $this->reservation !== null
            && $currentUser !== null
            && $currentUser->can('cancel', $this->reservation);
        $isCurrentUserAdmin = $currentUser?->hasRole('admin') ?? false;

        $globalCancellationCutoffHours = $request->attributes->get('global_cancellation_cutoff_hours');

        if (! is_int($globalCancellationCutoffHours)) {
            $globalCancellationCutoffHours = TerrainSetting::query()
                ->global()
                ->value('cancellation_cutoff_hours') ?? 0;
            $request->attributes->set('global_cancellation_cutoff_hours', $globalCancellationCutoffHours);
        }

        $canCancel = false;

        if ($isReservationCancellableByCurrentUser && $this->starts_at !== null) {
            if ($isCurrentUserAdmin) {
                $canCancel = true;
            } else {
                $now = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
                $slotStart = CarbonImmutable::parse(
                    $this->starts_at->toDateTimeString(),
                    self::BUSINESS_TIMEZONE,
                );

                $canCancel = $slotStart->isFuture()
                    && ($globalCancellationCutoffHours <= 0
                        || $now->addHours($globalCancellationCutoffHours)->lessThan($slotStart));
            }
        }

        return [
            'id' => $this->id,
            'terrain_id' => $this->terrain_id,
            'terrain' => TerrainResource::make($this->whenLoaded('terrain')),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->resolveDisplayStatus(),
            'reservation_id_for_current_user' => $this->when(
                $isReservationCancellableByCurrentUser,
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

    private function resolveDisplayStatus(): ?string
    {
        $status = $this->status?->value;

        if ($status !== ReservationSlotStatus::Available->value) {
            return $status;
        }

        if ($this->overlapsInactivePeriod()) {
            return ReservationSlotStatus::Blocked->value;
        }

        return $status;
    }

    private function overlapsInactivePeriod(): bool
    {
        $periods = $this->resolveInactivePeriodsForDay();

        if ($periods->isEmpty() || $this->starts_at === null || $this->ends_at === null) {
            return false;
        }

        $slotStartsAt = $this->starts_at->toDateTimeString();
        $slotEndsAt = $this->ends_at->toDateTimeString();

        return $periods->contains(
            fn (TerrainInactivePeriod $period): bool => $period->from_at < $slotEndsAt
                && $period->to_at > $slotStartsAt,
        );
    }

    /**
     * @return Collection<int, TerrainInactivePeriod>
     */
    private function resolveInactivePeriodsForDay(): Collection
    {
        $periods = request()->attributes->get('inactive_periods_for_day');

        if ($periods instanceof Collection) {
            return $periods;
        }

        if (is_array($periods)) {
            return collect($periods);
        }

        return collect();
    }
}
