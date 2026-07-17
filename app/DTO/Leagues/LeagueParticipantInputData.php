<?php

declare(strict_types=1);

namespace App\DTO\Leagues;

readonly class LeagueParticipantInputData
{
    public function __construct(
        public ?int $userId,
        public ?string $firstName,
        public ?string $lastName,
    ) {}
}
