<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class AddLeagueParticipantData
{
    public function __construct(
        public int $leagueId,
        public int $userId,
    ) {}
}
