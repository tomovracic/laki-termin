<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\DTO\Reservations\CreateReservationData;
use App\Enums\ReservationSlotStatus;
use App\Enums\ReservationStatus;
use App\Enums\ReservationTokenType;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\ReservationToken;
use App\Services\TerrainAvailabilityService;
use Illuminate\Database\DatabaseManager;

class CreateReservationAction
{
    public function __construct(
        protected DatabaseManager $database,
        protected TerrainAvailabilityService $availabilityService,
    ) {}

    public function execute(CreateReservationData $data): Reservation
    {
        return $this->database->transaction(function () use ($data): Reservation {
            /** @var ReservationSlot $slot */
            $slot = ReservationSlot::query()
                ->lockForUpdate()
                ->findOrFail($data->reservationSlotId);

            $this->availabilityService->assertSlotCanBeReserved($slot);

            $reservation = Reservation::query()->create([
                'user_id' => $data->userId,
                'reservation_slot_id' => $slot->id,
                'status' => ReservationStatus::Pending,
            ]);

            $slot->update([
                'status' => ReservationSlotStatus::Reserved,
            ]);

            $this->createToken($reservation, ReservationTokenType::Confirm, 24 * 60);
            $this->createToken($reservation, ReservationTokenType::Cancel, 24 * 60);

            return $reservation->load(['slot', 'tokens']);
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
