<?php

declare(strict_types=1);

namespace App\DTO\MatchHistory;

readonly class MatchHistoryPlayerInputData
{
    public function __construct(
        public ?int $userId,
        public ?string $firstName,
        public ?string $lastName,
    ) {}
}
