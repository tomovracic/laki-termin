<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class AddLeagueParticipantData
{
    public function __construct(
        public int $leagueId,
        public ?int $userId = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?LeagueParticipantInputData $partner = null,
    ) {}
}
