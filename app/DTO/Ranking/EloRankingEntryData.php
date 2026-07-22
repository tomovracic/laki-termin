<?php

declare(strict_types=1);

namespace App\DTO\Ranking;

readonly class EloRankingEntryData
{
    public function __construct(
        public int $userId,
        public string $firstName,
        public string $lastName,
        public string $name,
        public int $elo,
        public int $matchesPlayed,
        public int $wins,
        public int $losses,
    ) {}
}
