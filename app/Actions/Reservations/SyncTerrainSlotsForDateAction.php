<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Enums\ReservationSlotStatus;
use App\Models\ReservationSlot;
use App\Models\Terrain;
use App\Models\TerrainSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SyncTerrainSlotsForDateAction
{
    private const BUSINESS_TIMEZONE = 'Europe/Zagreb';

    public function execute(Terrain $terrain, CarbonImmutable $selectedDate): void
    {
        $setting = TerrainSetting::query()->global()->first();

        if ($setting === null) {
            return;
        }

        $nowInBusinessTimezone = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
        $periods = $this->normalizePeriods($setting->availability_periods ?? []);
        $allowedSlotKeys = [];
        $protectedSlots = $this->fetchProtectedSlots($terrain, $selectedDate);

        foreach ($periods as $period) {
            $periodStart = CarbonImmutable::parse($selectedDate->toDateString().' '.$period['from']);
            $periodEnd = CarbonImmutable::parse($selectedDate->toDateString().' '.$period['to']);
            $duration = $period['slot_duration_minutes'];

            for ($slotStart = $periodStart; $slotStart->addMinutes($duration)->lessThanOrEqualTo($periodEnd); $slotStart = $slotStart->addMinutes($duration)) {
                $slotEnd = $slotStart->addMinutes($duration);
                $slotStartInBusinessTimezone = CarbonImmutable::parse(
                    $slotStart->toDateTimeString(),
                    self::BUSINESS_TIMEZONE,
                );
                $isPastSlot = $slotStartInBusinessTimezone->lessThan($nowInBusinessTimezone);
                $expectedStatus = $isPastSlot
                    ? ReservationSlotStatus::Past
                    : ReservationSlotStatus::Available;

                $hasProtectedOverlap = $protectedSlots->contains(
                    fn (ReservationSlot $slot): bool => $slotStart->lessThan(CarbonImmutable::instance($slot->ends_at))
                        && $slotEnd->greaterThan(CarbonImmutable::instance($slot->starts_at))
                );

                if ($hasProtectedOverlap) {
                    continue;
                }

                $slotKey = $this->slotKey($slotStart, $slotEnd);
                $allowedSlotKeys[$slotKey] = true;

                $slot = ReservationSlot::query()->firstOrCreate(
                    [
                        'terrain_id' => $terrain->id,
                        'starts_at' => $slotStart->toDateTimeString(),
                        'ends_at' => $slotEnd->toDateTimeString(),
                    ],
                    [
                        'status' => $expectedStatus,
                    ],
                );

                if (
                    $slot->status !== ReservationSlotStatus::Reserved
                    && $slot->status !== ReservationSlotStatus::Blocked
                    && $slot->status !== ReservationSlotStatus::Maintenance
                    && $slot->status !== $expectedStatus
                ) {
                    $slot->update(['status' => $expectedStatus]);
                }
            }
        }

        $dayStartsAt = $selectedDate->startOfDay()->toDateTimeString();
        $dayEndsAt = $selectedDate->endOfDay()->toDateTimeString();

        ReservationSlot::query()
            ->forTerrain($terrain->id)
            ->between($dayStartsAt, $dayEndsAt)
            ->whereIn('status', [
                ReservationSlotStatus::Available->value,
                ReservationSlotStatus::Past->value,
            ])
            ->get()
            ->filter(fn (ReservationSlot $slot): bool => ! isset(
                $allowedSlotKeys[$this->slotKey(
                    CarbonImmutable::instance($slot->starts_at),
                    CarbonImmutable::instance($slot->ends_at),
                )]
            ))
            ->each(fn (ReservationSlot $slot): bool => $slot->delete());
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

    protected function slotKey(CarbonImmutable $slotStart, CarbonImmutable $slotEnd): string
    {
        return $slotStart->toDateTimeString().'|'.$slotEnd->toDateTimeString();
    }

    /**
     * @return Collection<int, ReservationSlot>
     */
    protected function fetchProtectedSlots(Terrain $terrain, CarbonImmutable $selectedDate): Collection
    {
        return ReservationSlot::query()
            ->forTerrain($terrain->id)
            ->between(
                $selectedDate->startOfDay()->toDateTimeString(),
                $selectedDate->endOfDay()->toDateTimeString(),
            )
            ->whereIn('status', [
                ReservationSlotStatus::Reserved->value,
                ReservationSlotStatus::Blocked->value,
                ReservationSlotStatus::Maintenance->value,
            ])
            ->get();
    }
}
