<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TerrainInactivePeriod */
class TerrainInactivePeriodResource extends JsonResource
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fromAt = CarbonImmutable::parse(
            $this->from_at->toDateTimeString(),
            self::BUSINESS_TIMEZONE,
        );
        $toAt = CarbonImmutable::parse(
            $this->to_at->toDateTimeString(),
            self::BUSINESS_TIMEZONE,
        );

        $isFullDay = $this->coversFullDay($fromAt, $toAt);

        return [
            'id' => $this->id,
            'terrain_id' => $this->terrain_id,
            'terrain_name' => $this->relationLoaded('terrain') ? $this->terrain?->name : null,
            'from_at' => $this->from_at,
            'to_at' => $this->to_at,
            'from_date' => $fromAt->toDateString(),
            'to_date' => $toAt->toDateString(),
            'block_type' => $isFullDay ? 'full_day' : 'time_range',
            'from_time' => $isFullDay ? null : $fromAt->format('H:i'),
            'to_time' => $isFullDay ? null : $toAt->format('H:i'),
            'reason' => $this->reason,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function coversFullDay(CarbonImmutable $fromAt, CarbonImmutable $toAt): bool
    {
        if (! $fromAt->isSameDay($toAt)) {
            return true;
        }

        return $fromAt->isStartOfDay() && $toAt->isEndOfDay();
    }
}
