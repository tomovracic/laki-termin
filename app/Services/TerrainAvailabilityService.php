<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReservationSlotStatus;
use App\Models\ReservationSlot;
use App\Models\TerrainInactivePeriod;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

class TerrainAvailabilityService
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    public function assertSlotCanBeReserved(ReservationSlot $slot): void
    {
        $slot->loadMissing('terrain');

        if (! $slot->terrain->is_active) {
            throw new DomainException('Selected terrain is currently inactive.');
        }

        if ($slot->status !== ReservationSlotStatus::Available) {
            throw new DomainException('Selected reservation slot is not available.');
        }

        $this->assertNoInactivePeriodOverlap($slot);
        $this->assertWithinReservationWindow($slot);
    }

    protected function assertNoInactivePeriodOverlap(ReservationSlot $slot): void
    {
        $overlapExists = TerrainInactivePeriod::query()
            ->where(function ($query) use ($slot): void {
                $query->whereNull('terrain_id')
                    ->orWhere('terrain_id', $slot->terrain_id);
            })
            ->overlapping(
                $slot->starts_at->toDateTimeString(),
                $slot->ends_at->toDateTimeString(),
            )
            ->exists();

        if ($overlapExists) {
            throw new DomainException('Selected slot overlaps an inactive terrain period.');
        }
    }

    protected function assertWithinReservationWindow(ReservationSlot $slot): void
    {
        $setting = TerrainSetting::query()->global()->first();

        if ($setting === null) {
            throw new DomainException('Reservation settings are not configured.');
        }

        $now = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
        $slotStart = CarbonImmutable::parse(
            $slot->starts_at->toDateTimeString(),
            self::BUSINESS_TIMEZONE,
        );

        if ($slotStart->lessThan($now)) {
            throw new DomainException('Slot start time has already passed.');
        }

        $daysAhead = $now->startOfDay()->diffInDays($slotStart->startOfDay(), false);

        if ($daysAhead < 0 || $daysAhead > $setting->max_advance_days) {
            throw new DomainException('Slot is outside allowed advance reservation range.');
        }

        $slotEnd = CarbonImmutable::parse(
            $slot->ends_at->toDateTimeString(),
            self::BUSINESS_TIMEZONE,
        );
        $periods = $this->normalizePeriods($setting->availability_periods ?? []);

        $isWithinPeriod = $periods->contains(function (array $period) use ($slotEnd, $slotStart): bool {
            $periodStart = CarbonImmutable::parse(
                $slotStart->toDateString().' '.$period['from'],
                self::BUSINESS_TIMEZONE,
            );
            $periodEnd = CarbonImmutable::parse(
                $slotStart->toDateString().' '.$period['to'],
                self::BUSINESS_TIMEZONE,
            );

            if ($slotStart->lessThan($periodStart) || $slotEnd->greaterThan($periodEnd)) {
                return false;
            }

            return true;
        });

        if (! $isWithinPeriod) {
            throw new DomainException('Slot is outside configured availability periods.');
        }
    }

    /**
     * @return Collection<int, array{from: string, to: string, slot_duration_minutes: int}>
     */
    protected function normalizePeriods(mixed $rawPeriods): Collection
    {
        if (! is_array($rawPeriods)) {
            return collect();
        }

        return collect($rawPeriods)
            ->filter(static fn (mixed $period): bool => is_array($period))
            ->map(static fn (array $period): array => [
                'from' => (string) ($period['from'] ?? ''),
                'to' => (string) ($period['to'] ?? ''),
                'slot_duration_minutes' => (int) ($period['slot_duration_minutes'] ?? 0),
            ])
            ->filter(static fn (array $period): bool => $period['from'] !== ''
                && $period['to'] !== ''
                && $period['slot_duration_minutes'] > 0)
            ->values();
    }
}
