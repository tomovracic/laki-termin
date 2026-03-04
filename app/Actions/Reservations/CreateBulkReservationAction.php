<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\DTO\Reservations\CreateBulkReservationData;
use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Enums\ReservationTokenType;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\ReservationToken;
use App\Models\User;
use App\Services\TerrainAvailabilityService;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class CreateBulkReservationAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected TerrainAvailabilityService $availabilityService,
    ) {}

    /**
     * @return Collection<int, Reservation>
     */
    public function execute(CreateBulkReservationData $data): Collection
    {
        return $this->database->transaction(function () use ($data): Collection {
            /** @var User $user */
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($data->userId);

            $requestedSlotIds = array_values(array_unique($data->reservationSlotIds));

            /** @var Collection<int, ReservationSlot> $slots */
            $slots = ReservationSlot::query()
                ->whereIn('id', $requestedSlotIds)
                ->with('terrain')
                ->lockForUpdate()
                ->get()
                ->sortBy('starts_at')
                ->values();

            if ($slots->count() !== count($requestedSlotIds)) {
                throw new DomainException('One or more selected slots are no longer available.');
            }

            if (($user->token_count ?? 0) < $slots->count()) {
                throw new DomainException('You do not have enough tokens for selected slots.');
            }

            /** @var Collection<int, Reservation> $reservations */
            $reservations = collect();

            foreach ($slots as $slot) {
                $this->availabilityService->assertSlotCanBeReserved($slot);

                $reservation = Reservation::query()->create([
                    'user_id' => $user->id,
                    'reservation_slot_id' => $slot->id,
                    'status' => ReservationStatus::Pending,
                ]);

                $slot->update([
                    'status' => ReservationSlotStatus::Reserved,
                ]);

                $this->createToken($reservation, ReservationTokenType::Confirm, 24 * 60);
                $this->createToken($reservation, ReservationTokenType::Cancel, 24 * 60);

                $reservations->push($reservation);
            }

            $user->update([
                'token_count' => max(0, ($user->token_count ?? 0) - $reservations->count()),
            ]);

            return $reservations->load(['slot.terrain', 'tokens']);
        });
    }

    protected function createToken(Reservation $reservation, ReservationTokenType $type, int $ttlMinutes): void
    {
        $plainToken = bin2hex(random_bytes(32));

        ReservationToken::query()->create([
            'reservation_id' => $reservation->id,
            'type' => $type,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
